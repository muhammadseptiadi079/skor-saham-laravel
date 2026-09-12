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
class BacktestService
{
    private const RANGEBOUND_THRESHOLD = 0.03; // +/-3% counts as "held" for a Hold/Netral label

    public function __construct(
        private YahooFinanceService $yahooFinance,
        private AlphaVantageService $alphaVantage,
    ) {}

    /** @return array{evaluated: int, skipped: int} */
    public function evaluate(int $minAgeDays = 28, int $limit = 50): array
    {
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
