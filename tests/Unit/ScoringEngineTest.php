<?php

namespace Tests\Unit;

use App\Services\ScoringEngine;
use PHPUnit\Framework\TestCase;

class ScoringEngineTest extends TestCase
{
    private ScoringEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ScoringEngine;
    }

    public function test_all_missing_data_yields_data_tidak_cukup(): void
    {
        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR');

        $this->assertNull($result['subScores']['fundamentals']['score']);
        $this->assertNull($result['subScores']['news']['score']);
        $this->assertNull($result['subScores']['momentum']['score']);
        $this->assertNull($result['subScores']['ownership']['score']);
        $this->assertNull($result['longterm']['score']);
        $this->assertSame('Data tidak cukup', $result['longterm']['label']);
        $this->assertNull($result['trading']['score']);
        $this->assertSame('Data tidak cukup', $result['trading']['label']);
    }

    public function test_strong_fundamentals_score_high_in_isolation(): void
    {
        $fundamentals = [
            'revenueGrowthYoy' => 0.2,
            'earningsGrowthYoy' => 0.2,
            'profitMargin' => 0.25,
            'returnOnEquity' => 0.25,
            'peRatio' => 10,
            'debtToEquity' => 0.2,
        ];

        $result = $this->engine->buildAnalysis($fundamentals, null, null, null, 'IDR');

        // 5 ratios score 1, debt-to-equity caps at 0.5 -> average = 5.5/6.
        $this->assertEqualsWithDelta(5.5 / 6, $result['subScores']['fundamentals']['score'], 0.0001);
        // Only fundamentals is available, so it fully determines both horizons.
        $this->assertEqualsWithDelta(5.5 / 6, $result['longterm']['score'], 0.0001);
        $this->assertSame('Strong Buy', $result['longterm']['label']);
    }

    public function test_weak_fundamentals_score_low_in_isolation(): void
    {
        $fundamentals = [
            'revenueGrowthYoy' => -0.2,
            'earningsGrowthYoy' => -0.2,
            'profitMargin' => -0.1,
            'returnOnEquity' => -0.1,
            'peRatio' => 50,
            'debtToEquity' => 2.0,
        ];

        $result = $this->engine->buildAnalysis($fundamentals, null, null, null, 'USD');

        $this->assertLessThan(-0.5, $result['subScores']['fundamentals']['score']);
        $this->assertSame('Strong Sell', $result['longterm']['label']);
    }

    public function test_news_score_averages_article_sentiment(): void
    {
        $articles = [
            ['title' => 'A', 'sentimentScore' => 1.0],
            ['title' => 'B', 'sentimentScore' => 0.5],
        ];

        $result = $this->engine->buildAnalysis(null, $articles, null, null, 'USD');

        $this->assertEqualsWithDelta(0.75, $result['subScores']['news']['score'], 0.0001);
        $this->assertCount(2, $result['subScores']['news']['topArticles']);
    }

    public function test_ownership_rewards_cluster_buying_by_multiple_insiders(): void
    {
        $transactions = [
            ['type' => 'buy', 'insiderName' => 'A', 'value' => 1_000_000, 'shares' => 100],
            ['type' => 'buy', 'insiderName' => 'B', 'value' => 1_000_000, 'shares' => 100],
            ['type' => 'buy', 'insiderName' => 'C', 'value' => 1_000_000, 'shares' => 100],
        ];

        $result = $this->engine->buildAnalysis(null, null, null, $transactions, 'IDR');

        $this->assertSame(1.0, $result['subScores']['ownership']['score']);
        $this->assertStringContainsString('beberapa orang berbeda', $result['subScores']['ownership']['notes'][1]);
    }

    public function test_momentum_requires_at_least_20_days(): void
    {
        $series = array_fill(0, 10, ['close' => 100, 'volume' => 1000]);

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertNull($result['subScores']['momentum']['score']);
    }

    public function test_momentum_adds_technical_indicator_notes_when_enough_history(): void
    {
        // 20 days, newest first, mild uptrend with small daily wiggles (not strictly monotonic,
        // so RSI isn't pinned to exactly 100/0 — closer to how real data behaves).
        $chrono = [];
        $price = 100;
        for ($i = 0; $i < 20; $i++) {
            $price += $i % 3 === 0 ? -0.3 : 1;
            $chrono[] = $price;
        }
        $series = [];
        foreach (array_reverse($chrono) as $close) {
            $series[] = ['close' => $close, 'volume' => 1_000_000];
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertNotNull($result['subScores']['momentum']['score']);
        // Base momentum note + RSI note at minimum (SMA50/MACD need more history than 20 days).
        $this->assertGreaterThanOrEqual(2, count($result['subScores']['momentum']['notes']));
        $this->assertStringContainsString('RSI(14)', implode(' ', $result['subScores']['momentum']['notes']));
    }

    public function test_peg_ratio_below_1_is_scored_positive(): void
    {
        $result = $this->engine->buildAnalysis(['pegRatio' => 0.8], null, null, null, 'USD');

        $this->assertSame(1.0, $result['subScores']['fundamentals']['score']);
        $this->assertStringContainsString('PEG ratio 0.80', $result['subScores']['fundamentals']['notes'][0]);
    }

    public function test_peg_ratio_above_2_is_scored_negative(): void
    {
        $result = $this->engine->buildAnalysis(['pegRatio' => 3.5], null, null, null, 'USD');

        $this->assertSame(-0.6, $result['subScores']['fundamentals']['score']);
    }

    public function test_sector_shown_as_context_note_without_affecting_score(): void
    {
        $withoutSector = $this->engine->buildAnalysis(['pegRatio' => 0.8], null, null, null, 'USD');
        $withSector = $this->engine->buildAnalysis(['pegRatio' => 0.8, 'sector' => 'Technology'], null, null, null, 'USD');

        $this->assertSame($withoutSector['subScores']['fundamentals']['score'], $withSector['subScores']['fundamentals']['score']);
        $this->assertStringContainsString('Sektor: Technology', end($withSector['subScores']['fundamentals']['notes']));
    }

    public function test_sector_note_shown_even_when_no_numeric_ratios_available(): void
    {
        $result = $this->engine->buildAnalysis(['sector' => 'Perbankan'], null, null, null, 'IDR');

        $this->assertNull($result['subScores']['fundamentals']['score']);
        $this->assertSame(['Sektor: Perbankan'], $result['subScores']['fundamentals']['notes']);
    }

    public function test_news_score_weights_reputable_sources_higher(): void
    {
        $articles = [
            ['title' => 'A', 'source' => 'Reuters', 'sentimentScore' => 1.0],
            ['title' => 'B', 'source' => 'Blog Kecil Random', 'sentimentScore' => -1.0],
        ];

        $result = $this->engine->buildAnalysis(null, $articles, null, null, 'USD');

        // Reuters (weight 1.3) pulls the average toward positive, unlike a plain 0.0 average.
        $this->assertGreaterThan(0, $result['subScores']['news']['score']);
    }

    public function test_momentum_adds_benchmark_relative_note_when_outperforming(): void
    {
        // Stock up ~10% over 20 days, benchmark flat -> stock clearly outperforms.
        $stockSeries = [];
        $benchmarkSeries = [];
        for ($i = 0; $i < 20; $i++) {
            $stockSeries[] = ['close' => 110 - $i * 0.5, 'volume' => 1_000_000];
            $benchmarkSeries[] = ['close' => 100, 'volume' => 1_000_000];
        }

        $result = $this->engine->buildAnalysis(null, null, $stockSeries, null, 'IDR', $benchmarkSeries, 'IHSG');

        $notes = implode(' ', $result['subScores']['momentum']['notes']);
        $this->assertStringContainsString('Mengungguli IHSG', $notes);
    }

    public function test_momentum_skips_benchmark_note_when_benchmark_data_missing(): void
    {
        $series = array_fill(0, 20, ['close' => 100, 'volume' => 1000]);

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR', null, 'IHSG');

        $notes = implode(' ', $result['subScores']['momentum']['notes']);
        $this->assertStringNotContainsString('IHSG', $notes);
    }

    public function test_low_liquidity_flag_set_when_average_daily_value_is_tiny(): void
    {
        // Rp1,000 close * 1,000 shares/day = Rp1,000,000/day, far below the Rp1B IDR threshold.
        $series = array_fill(0, 20, ['close' => 1000, 'volume' => 1000]);

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertTrue($result['lowLiquidity']);
        $this->assertStringContainsString('Likuiditas rendah', implode(' ', $result['subScores']['momentum']['notes']));
    }

    public function test_low_liquidity_flag_not_set_for_heavily_traded_stock(): void
    {
        // Rp1,000 close * 10,000,000 shares/day = Rp10B/day, well above the threshold.
        $series = array_fill(0, 20, ['close' => 1000, 'volume' => 10_000_000]);

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertFalse($result['lowLiquidity']);
        $this->assertStringNotContainsString('Likuiditas rendah', implode(' ', $result['subScores']['momentum']['notes']));
    }

    public function test_low_liquidity_uses_usd_threshold_for_global_market(): void
    {
        // $10 close * 1,000 shares/day = $10,000/day, below the $1M USD threshold.
        $series = array_fill(0, 20, ['close' => 10, 'volume' => 1000]);

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'USD');

        $this->assertTrue($result['lowLiquidity']);
    }

    public function test_long_term_trend_is_null_below_60_days_of_history(): void
    {
        $series = array_fill(0, 20, ['close' => 100, 'volume' => 1000]);

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertNull($result['subScores']['momentumLongTerm']['score']);
        $this->assertStringContainsString('60 hari', $result['subScores']['momentumLongTerm']['notes'][0]);
    }

    public function test_long_term_trend_scores_positive_for_a_sustained_uptrend(): void
    {
        // 100 days, oldest first internally: price roughly doubles over the window.
        $series = [];
        for ($i = 0; $i < 100; $i++) {
            $series[] = ['close' => 200 - $i, 'volume' => 1000]; // newest (i=0) highest
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertNotNull($result['subScores']['momentumLongTerm']['score']);
        $this->assertGreaterThan(0, $result['subScores']['momentumLongTerm']['score']);
        $this->assertStringContainsString('100 hari', implode(' ', $result['subScores']['momentumLongTerm']['notes']));
    }

    public function test_momentum_and_long_term_trend_use_independent_horizons(): void
    {
        // Chronologically (t=0 oldest .. t=99 newest): price climbs 100 -> 179 over 80 days, then
        // dips over the most recent 20 days down to 139. Long-term: clearly up overall. Short-term
        // (last 20 days only): clearly down. momentum (trading, 20d) should read bearish while
        // momentumLongTerm (full window) reads bullish.
        $pricesByAge = [];
        for ($t = 0; $t < 100; $t++) {
            $pricesByAge[$t] = $t <= 79 ? 100 + $t : 179 - ($t - 79) * 2;
        }
        $series = [];
        foreach (array_reverse($pricesByAge) as $close) {
            $series[] = ['close' => $close, 'volume' => 1000];
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertLessThan(0, $result['subScores']['momentum']['score']);
        $this->assertGreaterThan(0, $result['subScores']['momentumLongTerm']['score']);
    }

    public function test_analyst_target_price_above_current_price_scores_positive(): void
    {
        $series = [['close' => 100, 'volume' => 1000]]; // just needs [0]['close'] as "current price"

        $result = $this->engine->buildAnalysis(['analystTargetPrice' => 130], null, $series, null, 'USD');

        $this->assertGreaterThan(0, $result['subScores']['fundamentals']['score']);
        $this->assertStringContainsString('Target harga analis $130.00', $result['subScores']['fundamentals']['notes'][0]);
        $this->assertStringContainsString('naik 30.0%', $result['subScores']['fundamentals']['notes'][0]);
    }

    public function test_analyst_target_price_below_current_price_scores_negative(): void
    {
        $series = [['close' => 100, 'volume' => 1000]];

        $result = $this->engine->buildAnalysis(['analystTargetPrice' => 70], null, $series, null, 'USD');

        $this->assertLessThan(0, $result['subScores']['fundamentals']['score']);
    }

    public function test_earnings_within_14_days_adds_a_risk_note(): void
    {
        $soon = date('Y-m-d', strtotime('+5 days'));

        $result = $this->engine->buildAnalysis(['nextEarningsDate' => $soon], null, null, null, 'IDR');

        $this->assertStringContainsString('Laporan keuangan berikutnya', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_earnings_far_away_does_not_add_a_risk_note(): void
    {
        $farAway = date('Y-m-d', strtotime('+60 days'));

        $result = $this->engine->buildAnalysis(['nextEarningsDate' => $farAway, 'pegRatio' => 0.8], null, null, null, 'IDR');

        $this->assertStringNotContainsString('Laporan keuangan', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_data_completeness_counts_available_sub_scores(): void
    {
        $result = $this->engine->buildAnalysis(['pegRatio' => 0.8], null, null, null, 'IDR');

        $this->assertSame(1, $result['dataCompleteness']['available']);
        $this->assertSame(5, $result['dataCompleteness']['total']);
    }

    public function test_insider_time_decay_discounts_old_transactions(): void
    {
        $today = date('Y-m-d');
        $longAgo = date('Y-m-d', strtotime('-200 days'));

        // Equal raw buy/sell value would net to a neutral score (0), but the sell is old enough
        // (200 days) to be heavily discounted, so buying pressure should win out.
        $transactions = [
            ['type' => 'buy', 'insiderName' => 'A', 'value' => 1_000_000, 'shares' => 100, 'date' => $today],
            ['type' => 'sell', 'insiderName' => 'B', 'value' => 1_000_000, 'shares' => 100, 'date' => $longAgo],
        ];

        $result = $this->engine->buildAnalysis(null, null, null, $transactions, 'IDR');

        $this->assertGreaterThan(0.5, $result['subScores']['ownership']['score']);
        $this->assertStringContainsString('bobot lebih kecil', implode(' ', $result['subScores']['ownership']['notes']));
    }

    public function test_insider_transaction_without_date_is_not_penalized(): void
    {
        $transactions = [
            ['type' => 'buy', 'insiderName' => 'A', 'value' => 1_000_000, 'shares' => 100, 'date' => null],
            ['type' => 'sell', 'insiderName' => 'B', 'value' => 1_000_000, 'shares' => 100, 'date' => null],
        ];

        $result = $this->engine->buildAnalysis(null, null, null, $transactions, 'IDR');

        $this->assertEqualsWithDelta(0.0, $result['subScores']['ownership']['score'], 0.0001);
        $this->assertStringNotContainsString('bobot lebih kecil', implode(' ', $result['subScores']['ownership']['notes']));
    }

    public function test_data_completeness_is_full_when_everything_available(): void
    {
        $fundamentals = ['pegRatio' => 0.8];
        $articles = [['title' => 'A', 'sentimentScore' => 0.5]];
        $series = array_fill(0, 100, ['close' => 100, 'volume' => 1000]);
        $transactions = [['type' => 'buy', 'insiderName' => 'A', 'value' => 1000, 'shares' => 10, 'date' => null]];

        $result = $this->engine->buildAnalysis($fundamentals, $articles, $series, $transactions, 'IDR');

        $this->assertSame(5, $result['dataCompleteness']['available']);
        $this->assertSame(5, $result['dataCompleteness']['total']);
    }

    public function test_analyst_recommendation_trend_scores_positive_when_mostly_buy(): void
    {
        $fundamentals = ['analystRatings' => ['strongBuy' => 5, 'buy' => 3, 'hold' => 2, 'sell' => 0, 'strongSell' => 0]];

        $result = $this->engine->buildAnalysis($fundamentals, null, null, null, 'USD');

        $this->assertGreaterThan(0, $result['subScores']['fundamentals']['score']);
        $notes = implode(' ', $result['subScores']['fundamentals']['notes']);
        $this->assertStringContainsString('Rekomendasi analis: 5 Strong Buy, 3 Buy, 2 Hold, 0 Sell, 0 Strong Sell dari 10 analis', $notes);
    }

    public function test_analyst_recommendation_trend_scores_negative_when_mostly_sell(): void
    {
        $fundamentals = ['analystRatings' => ['strongBuy' => 0, 'buy' => 1, 'hold' => 1, 'sell' => 4, 'strongSell' => 4]];

        $result = $this->engine->buildAnalysis($fundamentals, null, null, null, 'USD');

        $this->assertLessThan(0, $result['subScores']['fundamentals']['score']);
    }

    public function test_analyst_ratings_all_zero_does_not_add_a_note(): void
    {
        $fundamentals = ['pegRatio' => 0.8, 'analystRatings' => ['strongBuy' => 0, 'buy' => 0, 'hold' => 0, 'sell' => 0, 'strongSell' => 0]];

        $result = $this->engine->buildAnalysis($fundamentals, null, null, null, 'USD');

        $this->assertStringNotContainsString('Rekomendasi analis', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_dividend_yield_shown_as_context_note_without_affecting_score(): void
    {
        $withoutDividend = $this->engine->buildAnalysis(['pegRatio' => 0.8], null, null, null, 'IDR');
        $withDividend = $this->engine->buildAnalysis(['pegRatio' => 0.8, 'dividendYield' => 0.032, 'payoutRatio' => 0.45], null, null, null, 'IDR');

        $this->assertSame($withoutDividend['subScores']['fundamentals']['score'], $withDividend['subScores']['fundamentals']['score']);
        $notes = implode(' ', $withDividend['subScores']['fundamentals']['notes']);
        $this->assertStringContainsString('Dividend yield 3.2%', $notes);
        $this->assertStringContainsString('rasio payout 45.0%', $notes);
    }

    public function test_high_payout_ratio_flagged_as_risk(): void
    {
        $result = $this->engine->buildAnalysis(['dividendYield' => 0.1, 'payoutRatio' => 0.95], null, null, null, 'IDR');

        $this->assertStringContainsString('risiko dividen dipotong', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_sector_relative_valuation_note_added_when_context_provided(): void
    {
        $sectorContext = ['sector' => 'Keuangan & Perbankan', 'avgPe' => 20.0, 'peSampleSize' => 3, 'avgPeg' => null, 'pegSampleSize' => 0];

        $result = $this->engine->buildAnalysis(['peRatio' => 10.0], null, null, null, 'IDR', null, null, $sectorContext);

        $notes = implode(' ', $result['subScores']['fundamentals']['notes']);
        $this->assertStringContainsString('P/E saham ini 10.0', $notes);
        $this->assertStringContainsString('lebih murah 50.0%', $notes);
    }

    public function test_no_sector_relative_note_when_context_missing(): void
    {
        $result = $this->engine->buildAnalysis(['peRatio' => 10.0], null, null, null, 'IDR');

        $this->assertStringNotContainsString('dibanding rata-rata', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_large_insider_transaction_relative_to_market_cap_flags_control_change(): void
    {
        $fundamentals = ['marketCap' => 10_000_000]; // one buy below is 10% of this
        $transactions = [
            ['type' => 'buy', 'insiderName' => 'Big Holdco', 'value' => 1_000_000, 'shares' => 1000],
        ];

        $result = $this->engine->buildAnalysis($fundamentals, null, null, $transactions, 'IDR');

        $this->assertStringContainsString('Kemungkinan pengalihan kepemilikan besar', implode(' ', $result['subScores']['ownership']['notes']));
        $this->assertStringContainsString('Big Holdco', implode(' ', $result['subScores']['ownership']['notes']));
    }

    public function test_small_insider_transaction_does_not_flag_control_change(): void
    {
        $fundamentals = ['marketCap' => 10_000_000_000]; // the same buy is now negligible
        $transactions = [
            ['type' => 'buy', 'insiderName' => 'Small Fry', 'value' => 1_000_000, 'shares' => 1000],
        ];

        $result = $this->engine->buildAnalysis($fundamentals, null, null, $transactions, 'IDR');

        $this->assertStringNotContainsString('pengalihan kepemilikan', implode(' ', $result['subScores']['ownership']['notes']));
    }

    public function test_control_change_flag_skipped_when_market_cap_unavailable(): void
    {
        $transactions = [
            ['type' => 'buy', 'insiderName' => 'Big Holdco', 'value' => 1_000_000, 'shares' => 1000],
        ];

        $result = $this->engine->buildAnalysis(null, null, null, $transactions, 'IDR');

        $this->assertStringNotContainsString('pengalihan kepemilikan', implode(' ', $result['subScores']['ownership']['notes']));
    }
}
