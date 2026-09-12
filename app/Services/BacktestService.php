<?php

namespace App\Services;

use App\Models\AnalysisHistory;
use Illuminate\Support\Facades\Log;

// Answers "does the app's rule-based scoring actually work?" by checking, once enough trading
// days have passed after a saved analysis, whether the ticker's price actually moved the
// direction its trading label predicted. This is the only honest way to know whether
// ScoringEngine's weights are any good — everything else is an educated guess about what should
// matter. Only grades the "trading" label, since its ~20-trading-day horizon is what this
// evaluation window matches; the "longterm" label is meant for a much longer horizon.
//
// Also tags each graded row with the market regime (bull/bear/sideways, by the benchmark's own
// move over the same window) it was evaluated in — a "Buy" call that was "right" only because the
// whole market rallied isn't the same kind of right as one that beat a flat or falling market.
class BacktestService
{
    private const RANGEBOUND_THRESHOLD = 0.03; // +/-3% counts as "held" for a Hold/Netral label

    private const BULL_THRESHOLD = 0.03; // benchmark up >3% over the window -> bull

    private const BEAR_THRESHOLD = -0.03; // benchmark down >3% over the window -> bear

    /** @var array<string, float|null> per-market benchmark close, fetched at most once per evaluate() run */
    private array $benchmarkCloseCache = [];

    public function __construct(
        private YahooFinanceService $yahooFinance,
        private AlphaVantageService $alphaVantage,
    ) {}

    /** @return array{evaluated: int, skipped: int} */
    public function evaluate(int $minAgeDays = 28, int $limit = 50): array
    {
        $this->benchmarkCloseCache = [];

        $candidates = AnalysisHistory::query()
            ->whereNull('evaluated_at')
            ->whereNotNull('price_at_generation')
            ->where('generated_at', '<=', now()->subDays($minAgeDays))
            ->orderBy('generated_at')
            ->limit($limit)
            ->get();

        $evaluated = 0;
        $skipped = 0;

        foreach ($candidates as $entry) {
            $latestClose = $this->getLatestClose($entry->ticker, $entry->market);
            if ($latestClose === null) {
                $skipped++;

                continue;
            }

            $forwardReturn = ($latestClose - $entry->price_at_generation) / $entry->price_at_generation;

            $entry->update([
                'forward_return' => $forwardReturn,
                'outcome_correct' => $this->wasCorrect($entry->trading_label, $forwardReturn),
                'market_regime' => $this->classifyRegime($entry->market, $entry->benchmark_price_at_generation),
                'evaluated_at' => now(),
            ]);
            $evaluated++;
        }

        return ['evaluated' => $evaluated, 'skipped' => $skipped];
    }

    // Null means "no directional prediction to grade" (e.g. "Data tidak cukup") — excluded from
    // accuracy stats rather than counted as wrong.
    private function wasCorrect(?string $label, float $forwardReturn): ?bool
    {
        return match ($label) {
            'Strong Buy', 'Buy' => $forwardReturn > 0,
            'Strong Sell', 'Sell' => $forwardReturn < 0,
            'Hold / Netral' => abs($forwardReturn) <= self::RANGEBOUND_THRESHOLD,
            default => null,
        };
    }

    private function classifyRegime(string $market, ?float $benchmarkPriceAtGeneration): ?string
    {
        if ($benchmarkPriceAtGeneration === null || $benchmarkPriceAtGeneration <= 0) {
            return null;
        }
        $benchmarkNow = $this->getBenchmarkClose($market);
        if ($benchmarkNow === null) {
            return null;
        }

        $benchmarkReturn = ($benchmarkNow - $benchmarkPriceAtGeneration) / $benchmarkPriceAtGeneration;

        return match (true) {
            $benchmarkReturn > self::BULL_THRESHOLD => 'bull',
            $benchmarkReturn < self::BEAR_THRESHOLD => 'bear',
            default => 'sideways',
        };
    }

    private function getBenchmarkClose(string $market): ?float
    {
        if (array_key_exists($market, $this->benchmarkCloseCache)) {
            return $this->benchmarkCloseCache[$market];
        }

        try {
            $close = $market === 'idx'
                ? ($this->yahooFinance->getChartForSymbol('^JKSE')['series'][0]['close'] ?? null)
                : ($this->alphaVantage->getDailyTimeSeries('SPY')[0]['close'] ?? null);
        } catch (\Throwable $e) {
            Log::warning("Backtest benchmark price fetch failed for {$market}: ".$e->getMessage());
            $close = null;
        }

        return $this->benchmarkCloseCache[$market] = $close;
    }

    private function getLatestClose(string $ticker, string $market): ?float
    {
        try {
            if ($market === 'idx') {
                $chart = $this->yahooFinance->getChart($ticker);

                return $chart['series'][0]['close'] ?? null;
            }

            $series = $this->alphaVantage->getDailyTimeSeries($ticker);

            return $series[0]['close'] ?? null;
        } catch (\Throwable $e) {
            Log::warning("Backtest price fetch failed for {$ticker} ({$market}): ".$e->getMessage());

            return null;
        }
    }
}
