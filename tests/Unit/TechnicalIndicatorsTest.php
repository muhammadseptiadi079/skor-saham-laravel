<?php

namespace Tests\Unit;

use App\Support\TechnicalIndicators;
use PHPUnit\Framework\TestCase;

class TechnicalIndicatorsTest extends TestCase
{
    public function test_sma_averages_the_last_n_closes(): void
    {
        $closes = range(1, 20); // 1..20, oldest first
        $this->assertSame(15.5, TechnicalIndicators::sma($closes, 10));
    }

    public function test_sma_returns_null_when_not_enough_data(): void
    {
        $this->assertNull(TechnicalIndicators::sma([1, 2, 3], 10));
    }

    public function test_rsi_is_100_for_a_strictly_rising_series(): void
    {
        $closes = range(1, 30); // no losses at all
        $this->assertSame(100.0, TechnicalIndicators::rsi($closes, 14));
    }

    public function test_rsi_is_0_for_a_strictly_falling_series(): void
    {
        $closes = range(30, 1); // no gains at all
        $this->assertSame(0.0, TechnicalIndicators::rsi($closes, 14));
    }

    public function test_rsi_returns_null_when_not_enough_data(): void
    {
        $this->assertNull(TechnicalIndicators::rsi(range(1, 10), 14));
    }

    public function test_macd_returns_null_when_not_enough_data(): void
    {
        $this->assertNull(TechnicalIndicators::macd(range(1, 30)));
    }

    public function test_macd_returns_macd_signal_and_histogram_for_a_rising_series(): void
    {
        $closes = range(1, 60);
        $result = TechnicalIndicators::macd($closes);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('macd', $result);
        $this->assertArrayHasKey('signal', $result);
        $this->assertArrayHasKey('histogram', $result);
        // A steadily rising price: the fast EMA leads the slow EMA, so MACD is positive.
        $this->assertGreaterThan(0, $result['macd']);
        $this->assertEqualsWithDelta($result['macd'] - $result['signal'], $result['histogram'], 0.0001);
    }

    public function test_swing_levels_returns_null_when_not_enough_data(): void
    {
        $this->assertNull(TechnicalIndicators::swingLevels(range(1, 5), 3));
    }

    public function test_swing_levels_detects_a_clear_high_and_low(): void
    {
        $closes = [...range(100, 109), 130, ...range(109, 100), 70, 71, 73, 75, 77, 79, 81, 83, 85];

        $levels = TechnicalIndicators::swingLevels($closes);

        $this->assertContains(130.0, array_map('floatval', $levels['highs']));
        $this->assertContains(70.0, array_map('floatval', $levels['lows']));
    }

    public function test_swing_levels_ignores_monotonic_series(): void
    {
        $levels = TechnicalIndicators::swingLevels(range(1, 30));

        $this->assertSame([], $levels['highs']);
        $this->assertSame([], $levels['lows']);
    }

    public function test_swing_points_carries_the_index_of_each_swing(): void
    {
        $closes = [...range(100, 109), 130, ...range(109, 100), 70, 71, 73, 75, 77, 79, 81, 83, 85];

        $points = TechnicalIndicators::swingPoints($closes);

        $this->assertSame(10, $points['highs'][0]['index']);
        $this->assertEqualsWithDelta(130.0, $points['highs'][0]['value'], 0.0001);
        $this->assertSame(21, $points['lows'][0]['index']);
        $this->assertEqualsWithDelta(70.0, $points['lows'][0]['value'], 0.0001);
    }

    public function test_rsi_series_matches_the_single_value_rsi_at_the_last_index(): void
    {
        $closes = range(1, 40);

        $series = TechnicalIndicators::rsiSeries($closes, 14);
        $single = TechnicalIndicators::rsi($closes, 14);

        $this->assertSame($single, $series[count($closes) - 1]);
    }

    public function test_rsi_series_is_null_before_enough_history(): void
    {
        $series = TechnicalIndicators::rsiSeries(range(1, 10), 14);

        $this->assertNull($series[9]);
    }
}
