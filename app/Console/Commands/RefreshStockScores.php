<?php

namespace App\Console\Commands;

use App\Models\AnalysisHistory;
use App\Models\StockUniverseItem;
use App\Models\WatchlistItem;
use App\Services\StockAnalysisService;
use Illuminate\Console\Command;

// Powers the gainers screener: recomputes scores for the user's watchlist plus the curated
// stock_universe (LQ45/blue-chip), caching results into analysis_history so /api/screener never
// has to compute anything live. Deliberately budget-limited on the global market since Alpha
// Vantage's free tier is 25 requests/day total and each global ticker costs ~3 of them
// (overview + news + daily time series).
class RefreshStockScores extends Command
{
    protected $signature = 'stocks:refresh-scores {--budget=20 : Max Alpha Vantage requests to spend on global tickers this run}';

    protected $description = 'Recompute and cache scores for the watchlist + curated universe (feeds the gainers screener)';

    private const AV_REQUESTS_PER_GLOBAL_TICKER = 3;

    public function handle(StockAnalysisService $analysisService): int
    {
        $tickers = $this->collectTickers();
        $budget = (int) $this->option('budget');
        $avSpent = 0;

        foreach ($tickers as $t) {
            if ($t['market'] === 'global') {
                if ($avSpent + self::AV_REQUESTS_PER_GLOBAL_TICKER > $budget) {
                    $this->warn("Skipping {$t['ticker']} (global) — Alpha Vantage budget of {$budget} requests reached for this run.");

                    continue;
                }
                $avSpent += self::AV_REQUESTS_PER_GLOBAL_TICKER;
            }

            try {
                $analysis = $analysisService->analyze($t['ticker'], $t['market']);
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
                $this->info("Scored {$t['ticker']} ({$t['market']}): trading={$analysis['trading']['label']}");
            } catch (\Throwable $e) {
                $this->error("Failed to score {$t['ticker']} ({$t['market']}): {$e->getMessage()}");
            }

            // Be polite to unofficial/free upstream endpoints regardless of market.
            usleep(300_000);
        }

        return self::SUCCESS;
    }

    /** @return array<int, array{ticker: string, market: string}> */
    private function collectTickers(): array
    {
        $watchlist = WatchlistItem::all(['ticker', 'market'])->map->only(['ticker', 'market']);
        $universe = StockUniverseItem::all(['ticker', 'market'])->map->only(['ticker', 'market']);

        return $watchlist->concat($universe)
            ->unique(fn ($t) => $t['ticker'].'|'.$t['market'])
            ->values()
            ->all();
    }
}
