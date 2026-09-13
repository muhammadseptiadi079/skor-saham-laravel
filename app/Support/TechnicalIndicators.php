<?php

namespace App\Support;

// Standard technical indicators computed from a chronological (oldest-first) list of closing
// prices. Kept separate from ScoringEngine so the math is unit-testable on its own, independent
// of any data source. MACD/EMA here use a simplified seed (first value, not a warm-up SMA) —
// close enough for a transparent rule-based signal, not meant to match a charting platform tick-for-tick.
class TechnicalIndicators
{
    public static function sma(array $closes, int $period): ?float
    {
        if (count($closes) < $period) {
            return null;
        }
        $slice = array_slice($closes, -$period);

        return array_sum($slice) / $period;
    }

    // Wilder's RSI. Needs at least $period+1 closes to produce the first average gain/loss.
    public static function rsi(array $closes, int $period = 14): ?float
    {
        if (count($closes) < $period + 1) {
            return null;
        }

        $changes = [];
        for ($i = 1; $i < count($closes); $i++) {
            $changes[] = $closes[$i] - $closes[$i - 1];
        }

        $avgGain = 0.0;
        $avgLoss = 0.0;
        for ($i = 0; $i < $period; $i++) {
            $c = $changes[$i];
            $avgGain += $c > 0 ? $c : 0;
            $avgLoss += $c < 0 ? -$c : 0;
        }
        $avgGain /= $period;
        $avgLoss /= $period;

        for ($i = $period; $i < count($changes); $i++) {
            $c = $changes[$i];
            $gain = $c > 0 ? $c : 0;
            $loss = $c < 0 ? -$c : 0;
            $avgGain = ($avgGain * ($period - 1) + $gain) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $loss) / $period;
        }

        if ($avgLoss == 0.0) {
            return 100.0;
        }
        $rs = $avgGain / $avgLoss;

        return 100 - (100 / (1 + $rs));
    }

    // Returns ['macd' => ..., 'signal' => ..., 'histogram' => ...] for the most recent point,
    // or null if there isn't enough history (needs at least $slow + $signalPeriod closes).
    public static function macd(array $closes, int $fast = 12, int $slow = 26, int $signalPeriod = 9): ?array
    {
        if (count($closes) < $slow + $signalPeriod) {
            return null;
        }

        $emaFast = self::emaSeries($closes, $fast);
        $emaSlow = self::emaSeries($closes, $slow);

        $macdLine = [];
        foreach ($closes as $i => $close) {
            $macdLine[] = $emaFast[$i] - $emaSlow[$i];
        }
        $signalLine = self::emaSeries($macdLine, $signalPeriod);

        $last = count($closes) - 1;

        return [
            'macd' => $macdLine[$last],
            'signal' => $signalLine[$last],
            'histogram' => $macdLine[$last] - $signalLine[$last],
        ];
    }

    // Classic swing high/low detection: a point is a swing high if it's strictly higher than every
    // other point within $lookback days on both sides (swing low: strictly lower). A simple,
    // widely-used chartist heuristic for "where did price turn around before" — not a prediction
    // that it will turn around there again. Returns null if there isn't enough history yet.
    public static function swingLevels(array $closesChrono, int $lookback = 3): ?array
    {
        $n = count($closesChrono);
        if ($n < $lookback * 2 + 1) {
            return null;
        }

        $highs = [];
        $lows = [];
        for ($i = $lookback; $i < $n - $lookback; $i++) {
            $window = array_slice($closesChrono, $i - $lookback, $lookback * 2 + 1);
            $point = $closesChrono[$i];
            if ($point === max($window) && array_sum(array_map(fn ($v) => $v === $point ? 1 : 0, $window)) === 1) {
                $highs[] = $point;
            }
            if ($point === min($window) && array_sum(array_map(fn ($v) => $v === $point ? 1 : 0, $window)) === 1) {
                $lows[] = $point;
            }
        }

        return ['highs' => $highs, 'lows' => $lows];
    }

    // Exponential moving average over the whole series, seeded with the first value.
    private static function emaSeries(array $values, int $period): array
    {
        $k = 2 / ($period + 1);
        $ema = [$values[0]];
        for ($i = 1; $i < count($values); $i++) {
            $ema[] = $values[$i] * $k + $ema[$i - 1] * (1 - $k);
        }

        return $ema;
    }
}
