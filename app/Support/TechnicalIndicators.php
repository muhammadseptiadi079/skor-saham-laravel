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
    // Keeps the index of each swing point (not just its price) so callers like divergence
    // detection can look up what another series (e.g. RSI) was doing at that same point in time.
    public static function swingPoints(array $closesChrono, int $lookback = 3): ?array
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
                $highs[] = ['index' => $i, 'value' => $point];
            }
            if ($point === min($window) && array_sum(array_map(fn ($v) => $v === $point ? 1 : 0, $window)) === 1) {
                $lows[] = ['index' => $i, 'value' => $point];
            }
        }

        return ['highs' => $highs, 'lows' => $lows];
    }

    public static function swingLevels(array $closesChrono, int $lookback = 3): ?array
    {
        $points = self::swingPoints($closesChrono, $lookback);
        if ($points === null) {
            return null;
        }

        return [
            'highs' => array_map(fn ($p) => $p['value'], $points['highs']),
            'lows' => array_map(fn ($p) => $p['value'], $points['lows']),
        ];
    }

    // Wilder's RSI computed at every point in the series (rather than just the latest, like rsi()
    // above) — needed to compare RSI's value at two different points in time, e.g. for divergence
    // detection. Index-aligned with $closes; entries before enough history exists are null.
    public static function rsiSeries(array $closes, int $period = 14): array
    {
        $n = count($closes);
        $series = array_fill(0, $n, null);
        if ($n < $period + 1) {
            return $series;
        }

        $changes = [];
        for ($i = 1; $i < $n; $i++) {
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
        $series[$period] = $avgLoss == 0.0 ? 100.0 : 100 - (100 / (1 + $avgGain / $avgLoss));

        for ($i = $period; $i < count($changes); $i++) {
            $c = $changes[$i];
            $gain = $c > 0 ? $c : 0;
            $loss = $c < 0 ? -$c : 0;
            $avgGain = ($avgGain * ($period - 1) + $gain) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $loss) / $period;
            $series[$i + 1] = $avgLoss == 0.0 ? 100.0 : 100 - (100 / (1 + $avgGain / $avgLoss));
        }

        return $series;
    }

    // On-Balance Volume: a running total that adds the day's volume when price closes up and
    // subtracts it when price closes down (unchanged closes leave it flat). The classic reading is
    // that OBV should broadly move the same direction as price — if it doesn't, the volume behind
    // the move disagrees with what the price alone suggests. $volumesChrono must be the same
    // length as $closesChrono and index-aligned with it (missing volume days should be passed as 0
    // by the caller, not omitted, or the two series drift out of alignment).
    public static function obv(array $closesChrono, array $volumesChrono): array
    {
        $n = count($closesChrono);
        $obv = array_fill(0, $n, 0.0);
        for ($i = 1; $i < $n; $i++) {
            if ($closesChrono[$i] > $closesChrono[$i - 1]) {
                $obv[$i] = $obv[$i - 1] + $volumesChrono[$i];
            } elseif ($closesChrono[$i] < $closesChrono[$i - 1]) {
                $obv[$i] = $obv[$i - 1] - $volumesChrono[$i];
            } else {
                $obv[$i] = $obv[$i - 1];
            }
        }

        return $obv;
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
