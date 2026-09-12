<?php

namespace Tests\Feature;

use App\Models\AnalysisHistory;
use App\Services\BacktestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BacktestServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeYahooChart(float $latestClose): void
    {
        Http::fake([
            'https://query1.finance.yahoo.com/v8/finance/chart/*' => Http::response([
                'chart' => ['result' => [[
                    'meta' => ['currency' => 'IDR', 'symbol' => 'BBCA.JK'],
                    'timestamp' => [1_700_000_000],
                    'indicators' => ['quote' => [['close' => [$latestClose], 'volume' => [1000]]]],
                ]]],
            ]),
        ]);
    }

    public function test_buy_label_graded_correct_when_price_rose(): void
    {
        $entry = AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'currency' => 'IDR',
            'trading_label' => 'Buy', 'trading_score' => 0.3,
            'price_at_generation' => 3000, 'evaluation_horizon_days' => 20,
            'generated_at' => now()->subDays(30),
        ]);
        $this->fakeYahooChart(3300); // +10%

        $result = app(BacktestService::class)->evaluate(minAgeDays: 28, limit: 50);

        $this->assertSame(['evaluated' => 1, 'skipped' => 0], $result);
        $entry->refresh();
        $this->assertEqualsWithDelta(0.10, $entry->forward_return, 0.0001);
        $this->assertTrue($entry->outcome_correct);
        $this->assertNotNull($entry->evaluated_at);
    }

    public function test_sell_label_graded_incorrect_when_price_rose(): void
    {
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'currency' => 'IDR',
            'trading_label' => 'Sell', 'trading_score' => -0.3,
            'price_at_generation' => 3000, 'evaluation_horizon_days' => 20,
            'generated_at' => now()->subDays(30),
        ]);
        $this->fakeYahooChart(3300);

        app(BacktestService::class)->evaluate(minAgeDays: 28, limit: 50);

        $this->assertFalse(AnalysisHistory::first()->outcome_correct);
    }

    public function test_data_tidak_cukup_label_is_not_graded(): void
    {
        AnalysisHistory::create([
            'ticker' => 'ZZZZ', 'market' => 'idx', 'currency' => 'IDR',
            'trading_label' => 'Data tidak cukup', 'trading_score' => null,
            'price_at_generation' => 100, 'evaluation_horizon_days' => 20,
            'generated_at' => now()->subDays(30),
        ]);
        $this->fakeYahooChart(150);

        app(BacktestService::class)->evaluate(minAgeDays: 28, limit: 50);

        $entry = AnalysisHistory::first();
        $this->assertNotNull($entry->evaluated_at);
        $this->assertNotNull($entry->forward_return);
        $this->assertNull($entry->outcome_correct);
    }

    public function test_too_recent_analysis_is_not_evaluated_yet(): void
    {
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'currency' => 'IDR',
            'trading_label' => 'Buy', 'trading_score' => 0.3,
            'price_at_generation' => 3000, 'evaluation_horizon_days' => 20,
            'generated_at' => now()->subDays(5),
        ]);
        $this->fakeYahooChart(3300);

        $result = app(BacktestService::class)->evaluate(minAgeDays: 28, limit: 50);

        $this->assertSame(['evaluated' => 0, 'skipped' => 0], $result);
        $this->assertNull(AnalysisHistory::first()->evaluated_at);
    }

    private function fakeYahooChartWithBenchmark(float $tickerClose, float $benchmarkClose): void
    {
        Http::fake([
            'https://query1.finance.yahoo.com/v8/finance/chart/*' => function ($request) use ($tickerClose, $benchmarkClose) {
                $isBenchmark = str_contains($request->url(), 'JKSE');
                $close = $isBenchmark ? $benchmarkClose : $tickerClose;

                return Http::response([
                    'chart' => ['result' => [[
                        'meta' => ['currency' => 'IDR', 'symbol' => 'X'],
                        'timestamp' => [1_700_000_000],
                        'indicators' => ['quote' => [['close' => [$close], 'volume' => [1000]]]],
                    ]]],
                ]);
            },
        ]);
    }

    public function test_market_regime_classified_bull_when_benchmark_rallied(): void
    {
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'currency' => 'IDR',
            'trading_label' => 'Buy', 'trading_score' => 0.3,
            'price_at_generation' => 3000, 'benchmark_price_at_generation' => 100,
            'evaluation_horizon_days' => 20, 'generated_at' => now()->subDays(30),
        ]);
        $this->fakeYahooChartWithBenchmark(tickerClose: 3300, benchmarkClose: 110); // benchmark +10%

        app(BacktestService::class)->evaluate(minAgeDays: 28, limit: 50);

        $this->assertSame('bull', AnalysisHistory::first()->market_regime);
    }

    public function test_market_regime_classified_bear_when_benchmark_fell(): void
    {
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'currency' => 'IDR',
            'trading_label' => 'Sell', 'trading_score' => -0.3,
            'price_at_generation' => 3000, 'benchmark_price_at_generation' => 100,
            'evaluation_horizon_days' => 20, 'generated_at' => now()->subDays(30),
        ]);
        $this->fakeYahooChartWithBenchmark(tickerClose: 2700, benchmarkClose: 90); // benchmark -10%

        app(BacktestService::class)->evaluate(minAgeDays: 28, limit: 50);

        $this->assertSame('bear', AnalysisHistory::first()->market_regime);
    }

    public function test_market_regime_classified_sideways_when_benchmark_flat(): void
    {
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'currency' => 'IDR',
            'trading_label' => 'Buy', 'trading_score' => 0.3,
            'price_at_generation' => 3000, 'benchmark_price_at_generation' => 100,
            'evaluation_horizon_days' => 20, 'generated_at' => now()->subDays(30),
        ]);
        $this->fakeYahooChartWithBenchmark(tickerClose: 3300, benchmarkClose: 101); // benchmark +1%

        app(BacktestService::class)->evaluate(minAgeDays: 28, limit: 50);

        $this->assertSame('sideways', AnalysisHistory::first()->market_regime);
    }

    public function test_market_regime_null_when_benchmark_price_at_generation_missing(): void
    {
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'currency' => 'IDR',
            'trading_label' => 'Buy', 'trading_score' => 0.3,
            'price_at_generation' => 3000, 'benchmark_price_at_generation' => null,
            'evaluation_horizon_days' => 20, 'generated_at' => now()->subDays(30),
        ]);
        $this->fakeYahooChart(3300);

        app(BacktestService::class)->evaluate(minAgeDays: 28, limit: 50);

        $this->assertNull(AnalysisHistory::first()->market_regime);
    }

    public function test_price_fetch_failure_is_skipped_and_left_for_retry(): void
    {
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'currency' => 'IDR',
            'trading_label' => 'Buy', 'trading_score' => 0.3,
            'price_at_generation' => 3000, 'evaluation_horizon_days' => 20,
            'generated_at' => now()->subDays(30),
        ]);
        Http::fake(['*' => Http::response([], 500)]);

        $result = app(BacktestService::class)->evaluate(minAgeDays: 28, limit: 50);

        $this->assertSame(['evaluated' => 0, 'skipped' => 1], $result);
        $this->assertNull(AnalysisHistory::first()->evaluated_at);
    }
}
