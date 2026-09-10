<?php

namespace App\Http\Controllers;

use App\Services\AlphaVantageService;
use App\Services\GoogleNewsRssService;
use App\Services\ScoringEngine;
use App\Services\SecEdgarService;
use App\Services\SentimentService;
use App\Services\YahooFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnalyzeController extends Controller
{
    public function __construct(
        private AlphaVantageService $alphaVantage,
        private YahooFinanceService $yahooFinance,
        private GoogleNewsRssService $googleNews,
        private SentimentService $sentiment,
        private SecEdgarService $secEdgar,
        private ScoringEngine $scoringEngine,
    ) {
    }

    public function analyze(Request $request)
    {
        $ticker = $request->query('ticker');
        $market = $request->query('market');

        if (!$ticker || !$market) {
            return response()->json([
                'error' => 'bad_request',
                'message' => 'Parameter ticker dan market wajib diisi.',
            ], 400);
        }
        if (!in_array($market, ['idx', 'global'], true)) {
            return response()->json([
                'error' => 'bad_request',
                'message' => 'market harus "idx" atau "global".',
            ], 400);
        }

        try {
            $data = $market === 'idx' ? $this->analyzeIdx($ticker) : $this->analyzeGlobal($ticker);

            $analysis = $this->scoringEngine->buildAnalysis(
                $data['fundamentals'],
                $data['newsArticles'],
                $data['priceSeries'],
                $data['ownershipTransactions'],
                $data['currency'],
            );

            return response()->json(array_merge([
                'ticker' => strtoupper($ticker),
                'market' => $market,
                'name' => $data['name'] ?? strtoupper($ticker),
                'generatedAt' => now()->toIso8601String(),
            ], $analysis));
        } catch (\Throwable $e) {
            Log::error('Analyze error: ' . $e->getMessage());
            return response()->json([
                'error' => 'upstream_error',
                'message' => 'Gagal mengambil data dari sumber eksternal. Coba lagi sebentar lagi, atau cek koneksi internet.',
            ], 502);
        }
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
            Log::warning('Data source fetch failed: ' . $e->getMessage());
            return null;
        }
    }
}
