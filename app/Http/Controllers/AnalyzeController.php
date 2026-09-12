<?php

namespace App\Http\Controllers;

use App\Models\AnalysisHistory;
use App\Services\StockAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnalyzeController extends Controller
{
    public function __construct(
        private StockAnalysisService $analysisService,
    ) {}

    public function analyze(Request $request)
    {
        $ticker = $request->query('ticker');
        $market = $request->query('market');

        if (! $ticker || ! $market) {
            return response()->json([
                'error' => 'bad_request',
                'message' => 'Parameter ticker dan market wajib diisi.',
            ], 400);
        }
        if (! in_array($market, ['idx', 'global'], true)) {
            return response()->json([
                'error' => 'bad_request',
                'message' => 'market harus "idx" atau "global".',
            ], 400);
        }

        try {
            $analysis = $this->analysisService->analyze($ticker, $market);
            $this->saveHistory($analysis);

            // These fields are only needed internally for the backtest/sector-valuation cache
            // (see saveHistory) — not part of the public response contract.
            return response()->json(collect($analysis)->except([
                'priceAtGeneration', 'benchmarkPriceAtGeneration', 'peRatioAtGeneration', 'pegRatioAtGeneration',
            ])->all());
        } catch (\Throwable $e) {
            Log::error('Analyze error: '.$e->getMessage());

            return response()->json([
                'error' => 'upstream_error',
                'message' => 'Gagal mengambil data dari sumber eksternal. Coba lagi sebentar lagi, atau cek koneksi internet.',
            ], 502);
        }
    }

    // Best-effort: a history-write failure should never take down the analyze response.
    private function saveHistory(array $analysis): void
    {
        try {
            AnalysisHistory::create([
                'ticker' => $analysis['ticker'],
                'market' => $analysis['market'],
                'name' => $analysis['name'] ?? null,
                'currency' => $analysis['currency'] ?? null,
                'longterm_score' => $analysis['longterm']['score'] ?? null,
                'longterm_label' => $analysis['longterm']['label'] ?? null,
                'trading_score' => $analysis['trading']['score'] ?? null,
                'trading_label' => $analysis['trading']['label'] ?? null,
                'sub_scores' => $analysis['subScores'] ?? null,
                'generated_at' => $analysis['generatedAt'],
                'price_at_generation' => $analysis['priceAtGeneration'] ?? null,
                'benchmark_price_at_generation' => $analysis['benchmarkPriceAtGeneration'] ?? null,
                'pe_ratio' => $analysis['peRatioAtGeneration'] ?? null,
                'peg_ratio' => $analysis['pegRatioAtGeneration'] ?? null,
                'evaluation_horizon_days' => 20,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to save analysis history: '.$e->getMessage());
        }
    }
}
