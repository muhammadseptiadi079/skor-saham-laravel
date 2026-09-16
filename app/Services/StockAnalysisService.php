<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Fetches data from the right sources for a ticker/market and runs it through ScoringEngine.
// Extracted from AnalyzeController so the same logic can be reused outside an HTTP request —
// e.g. by the stocks:refresh-scores command that powers the gainers screener.
class StockAnalysisService
{
    public function __construct(
        private AlphaVantageService $alphaVantage,
        private YahooFinanceService $yahooFinance,
        private GoogleNewsRssService $googleNews,
        private SentimentService $sentiment,
        private SecEdgarService $secEdgar,
        private ScoringEngine $scoringEngine,
        private SectorValuationService $sectorValuation,
        private NationalThemeService $nationalThemes,
        private NewsVolumeService $newsVolume,
        private ManualNewsService $manualNews,
    ) {}

    public function analyze(string $ticker, string $market): array
    {
        // Short-lived: covers an accidental double-click or the user re-checking the same stock
        // a minute later without re-hitting every external API (and, for global tickers, without
        // burning through Alpha Vantage's 25-requests/day free-tier limit on repeats). Long enough
        // to matter, short enough that "just analyzed" data never goes stale in a way anyone would
        // notice.
        $cacheKey = "stock_raw_data:{$market}:".strtoupper($ticker);
        $data = Cache::remember($cacheKey, now()->addMinutes(2), fn () => $market === 'idx' ? $this->analyzeIdx($ticker) : $this->analyzeGlobal($ticker));
        $sectorContext = $this->safe(fn () => $this->sectorValuation->averagesFor($ticker, $market));
        $sector = $this->safe(fn () => $this->sectorValuation->sectorFor($ticker, $market));
        $activeThemes = $this->safe(fn () => $this->nationalThemes->activeThemesForSector($sector, $market)) ?? [];
        $newsArticleCount = count($data['newsArticles'] ?? []);
        $newsVolumeNote = $this->safe(fn () => $this->newsVolume->spikeNoteFor($ticker, $market, $newsArticleCount));

        $analysis = $this->scoringEngine->buildAnalysis(
            $data['fundamentals'],
            $data['newsArticles'],
            $data['priceSeries'],
            $data['ownershipTransactions'],
            $data['currency'],
            $data['benchmarkSeries'],
            $data['benchmarkLabel'],
            $sectorContext,
            $activeThemes,
            $newsVolumeNote,
        );

        return array_merge([
            'ticker' => strtoupper($ticker),
            'market' => $market,
            'name' => $data['name'] ?? strtoupper($ticker),
            'currency' => $data['currency'],
            'generatedAt' => now()->toIso8601String(),
            // Captured so the backtest (BacktestService) can later compute a forward return
            // without re-fetching history from generation time.
            'priceAtGeneration' => $data['priceSeries'][0]['close'] ?? null,
            // Lets the backtest tell "this call was right because the whole market rallied" apart
            // from "this call was right on its own merits" — see BacktestService's regime check.
            'benchmarkPriceAtGeneration' => $data['benchmarkSeries'][0]['close'] ?? null,
            // Persisted so SectorValuationService can compare future analyses of other watchlist
            // stocks in the same sector against this one, without re-fetching fundamentals.
            'peRatioAtGeneration' => $data['fundamentals']['peRatio'] ?? null,
            'pegRatioAtGeneration' => $data['fundamentals']['pegRatio'] ?? null,
            // Persisted so NewsVolumeService can build a per-ticker baseline for future analyses.
            'newsArticleCountAtGeneration' => $newsArticleCount,
            // Persisted so SectorValuationService can also compare profitability (not just
            // valuation) against other watchlist stocks in the same sector.
            'profitMarginAtGeneration' => $data['fundamentals']['profitMargin'] ?? null,
            'roeAtGeneration' => $data['fundamentals']['returnOnEquity'] ?? null,
        ], $analysis);
    }

    private function analyzeGlobal(string $ticker): array
    {
        // The three Alpha Vantage calls are independent of each other, so they're fired
        // concurrently instead of waiting on each one in turn — previously the single biggest
        // source of a slow "Analisis" click. SEC EDGAR (insider tx) has its own internal
        // multi-step fetch (CIK -> filings -> per-filing docs, itself pooled — see
        // SecEdgarService) so it's kept as a separate call rather than folded into this pool.
        $responses = Http::pool(fn (Pool $pool) => [
            'overview' => $pool->as('overview')->get($this->alphaVantage->baseUrl(), $this->alphaVantage->overviewQuery($ticker)),
            'news' => $pool->as('news')->get($this->alphaVantage->baseUrl(), $this->alphaVantage->newsSentimentQuery($ticker)),
            'price' => $pool->as('price')->get($this->alphaVantage->baseUrl(), $this->alphaVantage->dailyTimeSeriesQuery($ticker)),
        ]);

        $overview = $this->safe(fn () => $this->alphaVantage->parseOverview($this->json($responses['overview']), $ticker));
        $newsArticles = $this->safe(fn () => $this->alphaVantage->parseNewsSentiment($this->json($responses['news']), $ticker)) ?? [];
        $priceSeries = $this->safe(fn () => $this->alphaVantage->parseDailyTimeSeries($this->json($responses['price'])));
        $insiderTx = $this->safe(fn () => $this->secEdgar->getInsiderTransactions($ticker));
        $manualArticles = $this->safe(fn () => $this->manualNews->recentArticlesFor($ticker, 'global')) ?? [];

        return [
            'name' => $overview['name'] ?? null,
            'fundamentals' => $overview,
            'newsArticles' => [...$manualArticles, ...$newsArticles],
            'priceSeries' => $priceSeries,
            'ownershipTransactions' => $insiderTx,
            'currency' => 'USD',
            'benchmarkSeries' => $this->getGlobalBenchmarkSeries(),
            'benchmarkLabel' => 'S&P 500',
        ];
    }

    private function analyzeIdx(string $ticker): array
    {
        $companyQuery = str_replace('.JK', '', $ticker);
        $symbol = $this->yahooFinance->normalizeIdxTicker($ticker);

        // Fundamentals and insider-tx are two separate quoteSummary requests (different
        // `modules`), plus the chart and news feed — all independent, so fired concurrently
        // instead of as four sequential round trips. This is the main fix for the "app terasa
        // lelet" (analysis feels sluggish) complaint: every single "Analisis" click used to wait
        // on this chain one request at a time.
        $responses = Http::pool(fn (Pool $pool) => [
            'fundamentals' => $pool->as('fundamentals')->withHeaders(YahooFinanceService::headers())
                ->get($this->yahooFinance->fundamentalsUrl($symbol), $this->yahooFinance->fundamentalsQuery()),
            'insider' => $pool->as('insider')->withHeaders(YahooFinanceService::headers())
                ->get($this->yahooFinance->fundamentalsUrl($symbol), $this->yahooFinance->insiderTransactionsQuery()),
            'chart' => $pool->as('chart')->withHeaders(YahooFinanceService::headers())
                ->get($this->yahooFinance->chartUrl($symbol), $this->yahooFinance->chartQuery()),
            'news' => $pool->as('news')->withHeaders(GoogleNewsRssService::headers())
                ->get($this->googleNews->url(), $this->googleNews->query("saham {$companyQuery}")),
        ]);

        $fundamentals = $this->safe(fn () => $this->yahooFinance->parseFundamentals($this->json($responses['fundamentals'])));
        $chart = $this->safe(fn () => $this->yahooFinance->parseChart($this->json($responses['chart']), $symbol));
        $newsRaw = $this->safe(fn () => $this->googleNews->parseNewsXml($this->body($responses['news']))) ?? [];
        $insiderTx = $this->safe(fn () => $this->yahooFinance->parseInsiderTransactions($this->json($responses['insider'])));

        $scored = $this->sentiment->scoreArticles($newsRaw);
        $manualArticles = $this->safe(fn () => $this->manualNews->recentArticlesFor($ticker, 'idx')) ?? [];

        return [
            'name' => $chart['name'] ?? $this->yahooFinance->normalizeIdxTicker($ticker),
            'fundamentals' => $fundamentals,
            'newsArticles' => [...$manualArticles, ...$scored],
            'priceSeries' => $chart['series'] ?? null,
            'ownershipTransactions' => $insiderTx,
            'currency' => 'IDR',
            'benchmarkSeries' => $this->getIdxBenchmarkSeries(),
            'benchmarkLabel' => 'IHSG',
        ];
    }

    // Benchmark series are shared by every ticker analyzed on a given day, so they're cached
    // instead of being fetched fresh per ticker — important for the global market especially,
    // since Alpha Vantage's free tier only allows 25 requests/day total.
    private function getIdxBenchmarkSeries(): ?array
    {
        return Cache::remember('benchmark_series_idx_jkse', now()->addHours(12), function () {
            $chart = $this->safe(fn () => $this->yahooFinance->getChartForSymbol('^JKSE'));

            return $chart['series'] ?? null;
        });
    }

    private function getGlobalBenchmarkSeries(): ?array
    {
        return Cache::remember('benchmark_series_global_spy', now()->addHours(12), function () {
            return $this->safe(fn () => $this->alphaVantage->getDailyTimeSeries('SPY'));
        });
    }

    // Mirrors the Node version's Promise.allSettled behavior: one failing data source
    // degrades that sub-score to "unavailable" instead of failing the whole analysis.
    private function safe(\Closure $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            Log::warning('Data source fetch failed: '.$e->getMessage());

            return null;
        }
    }

    // A pooled request that fails to connect comes back as an exception object in that slot
    // instead of a Response (Http::pool() never throws itself) — these turn that into the same
    // "null, handled by safe()" shape a normal try/catch around a blocking Http::get() would.
    private function json($pooled): ?array
    {
        return $pooled instanceof Response ? $pooled->json() : null;
    }

    private function body($pooled): string
    {
        return $pooled instanceof Response ? $pooled->body() : '';
    }
}
