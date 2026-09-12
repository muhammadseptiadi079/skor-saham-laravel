<?php

namespace App\Services;

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
        );

        return array_merge([
            'ticker' => strtoupper($ticker),
            'market' => $market,
            'name' => $data['name'] ?? strtoupper($ticker),
            'currency' => $data['currency'],
            'generatedAt' => now()->toIso8601String(),
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
        ];
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
