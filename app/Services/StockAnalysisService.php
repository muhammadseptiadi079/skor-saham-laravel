<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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
    ) {}

    public function analyze(string $ticker, string $market): array
    {
        $data = $market === 'idx' ? $this->analyzeIdx($ticker) : $this->analyzeGlobal($ticker);

        $analysis = $this->scoringEngine->buildAnalysis(
            $data['fundamentals'],
            $data['newsArticles'],
            $data['priceSeries'],
            $data['ownershipTransactions'],
            $data['currency'],
            $data['benchmarkSeries'],
            $data['benchmarkLabel'],
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
        ], $analysis);
    }

    private function analyzeGlobal(string $ticker): array
    {
        $overview = $this->safe(fn () => $this->alphaVantage->getOverview($ticker));
        $newsArticles = $this->safe(fn () => $this->alphaVantage->getNewsSentiment($ticker)) ?? [];
        $priceSeries = $this->safe(fn () => $this->alphaVantage->getDailyTimeSeries($ticker));
        $insiderTx = $this->safe(fn () => $this->secEdgar->getInsiderTransactions($ticker));

        return [
            'name' => $overview['name'] ?? null,
            'fundamentals' => $overview,
            'newsArticles' => $newsArticles,
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

        $fundamentals = $this->safe(fn () => $this->yahooFinance->getFundamentals($ticker));
        $chart = $this->safe(fn () => $this->yahooFinance->getChart($ticker));
        $newsRaw = $this->safe(fn () => $this->googleNews->getNews("saham {$companyQuery}")) ?? [];
        $insiderTx = $this->safe(fn () => $this->yahooFinance->getInsiderTransactions($ticker));

        $scored = $this->sentiment->scoreArticles($newsRaw);

        return [
            'name' => $chart['name'] ?? $this->yahooFinance->normalizeIdxTicker($ticker),
            'fundamentals' => $fundamentals,
            'newsArticles' => $scored,
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
}
