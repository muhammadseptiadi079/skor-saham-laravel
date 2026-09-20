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

    public function test_price_target_is_surfaced_at_the_top_level(): void
    {
        $series = [['close' => 100, 'volume' => 1000]];

        $result = $this->engine->buildAnalysis(['analystTargetPrice' => 130], null, $series, null, 'USD');

        $this->assertEquals(130, $result['priceTarget']['targetPrice']);
        $this->assertEqualsWithDelta(0.3, $result['priceTarget']['upsidePct'], 0.0001);
    }

    public function test_price_target_is_null_when_no_analyst_target_available(): void
    {
        $series = [['close' => 100, 'volume' => 1000]];

        $result = $this->engine->buildAnalysis(['peRatio' => 12], null, $series, null, 'USD');

        $this->assertNull($result['priceTarget']);
    }

    public function test_price_target_is_null_when_fundamentals_unavailable(): void
    {
        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR');

        $this->assertNull($result['priceTarget']);
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
        $this->assertSame(6, $result['dataCompleteness']['total']);
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
        $this->assertSame(6, $result['dataCompleteness']['total']);
    }

    public function test_data_completeness_is_full_including_foreign_flow_when_everything_available(): void
    {
        $fundamentals = ['pegRatio' => 0.8];
        $articles = [['title' => 'A', 'sentimentScore' => 0.5]];
        $series = array_fill(0, 100, ['close' => 100, 'volume' => 1000]);
        $transactions = [['type' => 'buy', 'insiderName' => 'A', 'value' => 1000, 'shares' => 10, 'date' => null]];
        $foreignFlow = ['buyValue' => 1_000_000, 'sellValue' => 200_000, 'asOfDate' => '2024-01-02'];

        $result = $this->engine->buildAnalysis(
            $fundamentals, $articles, $series, $transactions, 'IDR',
            null, null, null, [], null, $foreignFlow
        );

        $this->assertSame(6, $result['dataCompleteness']['available']);
        $this->assertSame(6, $result['dataCompleteness']['total']);
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

    public function test_active_national_theme_shown_as_context_note_without_affecting_score(): void
    {
        $activeThemes = [['note' => 'Lagi ramai berita kebakaran hutan/kabut asap — ...']];

        $withoutTheme = $this->engine->buildAnalysis(['pegRatio' => 0.8], null, null, null, 'IDR');
        $withTheme = $this->engine->buildAnalysis(['pegRatio' => 0.8], null, null, null, 'IDR', null, null, null, $activeThemes);

        $this->assertSame($withoutTheme['subScores']['fundamentals']['score'], $withTheme['subScores']['fundamentals']['score']);
        $this->assertStringContainsString('kebakaran hutan', implode(' ', $withTheme['subScores']['fundamentals']['notes']));
    }

    public function test_active_national_theme_shown_even_when_fundamentals_data_unavailable(): void
    {
        $activeThemes = [['note' => 'Lagi ramai berita kemarau panjang/kekeringan — ...']];

        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR', null, null, null, $activeThemes);

        $this->assertNull($result['subScores']['fundamentals']['score']);
        $this->assertStringContainsString('kemarau panjang', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_no_national_theme_note_when_no_active_themes(): void
    {
        $result = $this->engine->buildAnalysis(['pegRatio' => 0.8], null, null, null, 'IDR');

        $this->assertStringNotContainsString('Lagi ramai berita', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_high_beta_shown_as_more_volatile_context_note(): void
    {
        $result = $this->engine->buildAnalysis(['beta' => 1.5], null, null, null, 'USD');

        $notes = implode(' ', $result['subScores']['fundamentals']['notes']);
        $this->assertStringContainsString('Beta 1.50', $notes);
        $this->assertStringContainsString('lebih volatile dari pasar', $notes);
    }

    public function test_low_beta_shown_as_defensive_context_note(): void
    {
        $result = $this->engine->buildAnalysis(['beta' => 0.5], null, null, null, 'USD');

        $this->assertStringContainsString('defensif', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_52_week_position_shown_near_high(): void
    {
        $series = [['close' => 115, 'volume' => 1000]];

        $result = $this->engine->buildAnalysis(
            ['fiftyTwoWeekLow' => 80, 'fiftyTwoWeekHigh' => 120], null, $series, null, 'USD'
        );

        $notes = implode(' ', $result['subScores']['fundamentals']['notes']);
        $this->assertStringContainsString('87.5%', $notes);
        $this->assertStringContainsString('dekat titik tertinggi 52 minggu', $notes);
    }

    public function test_52_week_position_shown_near_low(): void
    {
        $series = [['close' => 85, 'volume' => 1000]];

        $result = $this->engine->buildAnalysis(
            ['fiftyTwoWeekLow' => 80, 'fiftyTwoWeekHigh' => 120], null, $series, null, 'USD'
        );

        $this->assertStringContainsString('dekat titik terendah 52 minggu', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_historical_volatility_note_added_when_enough_price_history(): void
    {
        $chrono = [];
        $price = 100;
        for ($i = 0; $i < 20; $i++) {
            $price += $i % 2 === 0 ? 3 : -2;
            $chrono[] = $price;
        }
        $series = [];
        foreach (array_reverse($chrono) as $close) {
            $series[] = ['close' => $close, 'volume' => 1_000_000];
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertStringContainsString('Volatilitas historis', implode(' ', $result['subScores']['momentum']['notes']));
    }

    public function test_ara_arb_warning_shown_for_large_single_day_move_on_idx(): void
    {
        // 20 days flat at 1000, except today's close jumped to 1200 (+20%) — band for a
        // Rp200-5000 previous close is 25%, and 20% eats 80% of that band (>= the 70% trigger).
        $series = array_fill(0, 20, ['close' => 1000, 'volume' => 5_000_000]);
        $series[0] = ['close' => 1200, 'volume' => 5_000_000];

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $notes = implode(' ', $result['subScores']['momentum']['notes']);
        $this->assertStringContainsString('ARA (auto reject atas)', $notes);
    }

    public function test_ara_arb_warning_not_shown_for_small_move(): void
    {
        $series = array_fill(0, 20, ['close' => 1000, 'volume' => 5_000_000]);
        $series[0] = ['close' => 1010, 'volume' => 5_000_000];

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $notes = implode(' ', $result['subScores']['momentum']['notes']);
        $this->assertStringNotContainsString('auto reject', $notes);
    }

    public function test_ara_arb_warning_never_shown_for_global_market(): void
    {
        $series = array_fill(0, 20, ['close' => 1000, 'volume' => 5_000_000]);
        $series[0] = ['close' => 1200, 'volume' => 5_000_000];

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'USD');

        $notes = implode(' ', $result['subScores']['momentum']['notes']);
        $this->assertStringNotContainsString('auto reject', $notes);
    }

    public function test_news_volume_spike_note_appended_to_news_notes(): void
    {
        $articles = [['title' => 'A', 'sentimentScore' => 0.5]];

        $result = $this->engine->buildAnalysis(
            null, $articles, null, null, 'USD', null, null, null, [], 'Jumlah berita lagi tinggi'
        );

        $this->assertStringContainsString('Jumlah berita lagi tinggi', implode(' ', $result['subScores']['news']['notes']));
    }

    public function test_news_volume_spike_note_shown_even_with_no_articles(): void
    {
        $result = $this->engine->buildAnalysis(
            null, [], null, null, 'USD', null, null, null, [], 'Jumlah berita lagi tinggi'
        );

        $this->assertStringContainsString('Jumlah berita lagi tinggi', implode(' ', $result['subScores']['news']['notes']));
    }

    public function test_ex_dividend_date_within_7_days_adds_a_note(): void
    {
        $soon = date('Y-m-d', strtotime('+3 days'));

        $result = $this->engine->buildAnalysis(['exDividendDate' => $soon], null, null, null, 'IDR');

        $this->assertStringContainsString('Ex Dividen', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_ex_dividend_date_far_away_does_not_add_a_note(): void
    {
        $farAway = date('Y-m-d', strtotime('+30 days'));

        $result = $this->engine->buildAnalysis(['exDividendDate' => $farAway, 'pegRatio' => 0.8], null, null, null, 'IDR');

        $this->assertStringNotContainsString('Ex Dividen', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_ownership_percentage_shown_as_context_note(): void
    {
        $result = $this->engine->buildAnalysis(
            ['insidersPercentHeld' => 0.15, 'institutionsPercentHeld' => 0.42], null, null, null, 'USD'
        );

        $notes = implode(' ', $result['subScores']['fundamentals']['notes']);
        $this->assertStringContainsString('insider 15.0%', $notes);
        $this->assertStringContainsString('institusi 42.0%', $notes);
    }

    public function test_support_resistance_note_shown_from_swing_points(): void
    {
        $chrono = [];
        foreach (range(100, 109) as $v) {
            $chrono[] = $v;
        }
        $chrono[] = 130; // swing high
        foreach (range(109, 100) as $v) {
            $chrono[] = $v;
        }
        $chrono[] = 70; // swing low
        foreach ([71, 73, 75, 77, 79, 81, 83, 85] as $v) {
            $chrono[] = $v;
        }

        $series = [];
        foreach (array_reverse($chrono) as $close) {
            $series[] = ['close' => $close, 'volume' => 1_000_000];
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $notes = implode(' ', $result['subScores']['momentum']['notes']);
        $this->assertStringContainsString('resistance terdekat ~Rp130', $notes);
        $this->assertStringContainsString('support terdekat ~Rp70', $notes);
    }

    public function test_risk_adjusted_momentum_note_shown_when_volatility_available(): void
    {
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

        $this->assertStringContainsString('Rasio return-terhadap-risiko', implode(' ', $result['subScores']['momentum']['notes']));
    }

    public function test_risk_adjusted_momentum_note_skipped_for_flat_price_with_zero_volatility(): void
    {
        $series = array_fill(0, 20, ['close' => 100, 'volume' => 1_000_000]);

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertStringNotContainsString('Rasio return-terhadap-risiko', implode(' ', $result['subScores']['momentum']['notes']));
    }

    public function test_bearish_divergence_detected_and_scored_negative(): void
    {
        $lead = [95, 96, 95, 96, 95, 96, 95, 96, 95, 96, 95, 96, 95, 96];
        $a = [100, 105, 110, 115, 120, 125, 130, 135, 140, 145, 150]; // strong rise -> swing high, high RSI
        $b = [148, 146, 144, 142, 140, 138, 136];
        $c = [138, 140, 139, 141, 140, 142, 141, 143, 142, 144, 143, 145, 144, 146, 145, 147, 146, 148, 147, 152]; // choppy weak rise -> higher price, lower RSI
        $tail = [150, 148, 146, 144];
        $chrono = [...$lead, ...$a, ...$b, ...$c, ...$tail];

        $series = [];
        foreach (array_reverse($chrono) as $close) {
            $series[] = ['close' => $close, 'volume' => 1_000_000];
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertStringContainsString('Divergence bearish', implode(' ', $result['subScores']['momentum']['notes']));
    }

    public function test_bullish_divergence_detected_and_scored_positive(): void
    {
        $lead = [95, 96, 95, 96, 95, 96, 95, 96, 95, 96, 95, 96, 95, 96];
        $a = [100, 95, 90, 85, 80, 75, 70, 65, 60, 55, 50]; // strong decline -> swing low, low RSI
        $b = [52, 54, 56, 58, 60, 62, 64];
        $c = [64, 62, 63, 61, 62, 60, 61, 59, 60, 58, 59, 57, 58, 56, 57, 55, 56, 54, 55, 48]; // choppy weak decline -> lower price, higher RSI
        $tail = [50, 52, 54, 56];
        $chrono = [...$lead, ...$a, ...$b, ...$c, ...$tail];

        $series = [];
        foreach (array_reverse($chrono) as $close) {
            $series[] = ['close' => $close, 'volume' => 1_000_000];
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertStringContainsString('Divergence bullish', implode(' ', $result['subScores']['momentum']['notes']));
    }

    public function test_no_divergence_note_for_a_plain_uptrend(): void
    {
        $chrono = [];
        $price = 100;
        for ($i = 0; $i < 40; $i++) {
            $price += 1;
            $chrono[] = $price;
        }
        $series = [];
        foreach (array_reverse($chrono) as $close) {
            $series[] = ['close' => $close, 'volume' => 1_000_000];
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertStringNotContainsString('Divergence', implode(' ', $result['subScores']['momentum']['notes']));
    }

    public function test_confidence_is_high_when_available_sub_scores_all_agree(): void
    {
        $fundamentals = ['pegRatio' => 0.8]; // positive
        $articles = [['title' => 'A', 'sentimentScore' => 1.0]]; // positive
        $transactions = [['type' => 'buy', 'insiderName' => 'A', 'value' => 1_000_000, 'shares' => 100]]; // positive
        $series = [];
        for ($i = 0; $i < 100; $i++) {
            $series[] = ['close' => 200 - $i, 'volume' => 1000]; // sustained uptrend -> momentumLongTerm positive
        }

        $result = $this->engine->buildAnalysis($fundamentals, $articles, $series, $transactions, 'IDR');

        $this->assertSame('Tinggi', $result['longterm']['confidence']);
        $this->assertStringContainsString('sepakat dengan arah kesimpulan', $result['longterm']['confidenceNote']);
    }

    public function test_confidence_is_moderate_when_one_sub_score_disagrees(): void
    {
        $fundamentals = ['pegRatio' => 0.8]; // positive
        $articles = [['title' => 'A', 'sentimentScore' => 1.0]]; // positive
        $transactions = [['type' => 'buy', 'insiderName' => 'A', 'value' => 1_000_000, 'shares' => 100]]; // positive
        $series = [];
        for ($i = 0; $i < 100; $i++) {
            $series[] = ['close' => 100 + $i, 'volume' => 1000]; // sustained downtrend -> momentumLongTerm negative
        }

        $result = $this->engine->buildAnalysis($fundamentals, $articles, $series, $transactions, 'IDR');

        $this->assertSame('Sedang', $result['longterm']['confidence']);
    }

    public function test_confidence_is_null_when_no_data_available(): void
    {
        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR');

        $this->assertNull($result['longterm']['confidence']);
        $this->assertNull($result['longterm']['confidenceNote']);
    }

    public function test_sector_relative_profit_margin_note_added_when_context_provided(): void
    {
        $sectorContext = [
            'sector' => 'Keuangan & Perbankan', 'avgPe' => null, 'peSampleSize' => 0,
            'avgPeg' => null, 'pegSampleSize' => 0,
            'avgProfitMargin' => 0.15, 'profitMarginSampleSize' => 3,
            'avgRoe' => 0.10, 'roeSampleSize' => 3,
        ];

        $result = $this->engine->buildAnalysis(
            ['profitMargin' => 0.25, 'returnOnEquity' => 0.05], null, null, null, 'IDR', null, null, $sectorContext
        );

        $notes = implode(' ', $result['subScores']['fundamentals']['notes']);
        $this->assertStringContainsString('Margin laba saham ini 25.0%', $notes);
        $this->assertStringContainsString('lebih tinggi', $notes);
        $this->assertStringContainsString('ROE saham ini 5.0%', $notes);
        $this->assertStringContainsString('lebih rendah', $notes);
    }

    public function test_horizon_alignment_is_null_when_either_score_is_unavailable(): void
    {
        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR');

        $this->assertNull($result['horizonAlignment']['aligned']);
        $this->assertNull($result['horizonAlignment']['note']);
    }

    public function test_horizon_alignment_is_true_when_both_horizons_agree(): void
    {
        $fundamentals = [
            'revenueGrowthYoy' => 0.2, 'earningsGrowthYoy' => 0.2, 'profitMargin' => 0.25,
            'returnOnEquity' => 0.25, 'peRatio' => 10, 'debtToEquity' => 0.2,
        ];

        $result = $this->engine->buildAnalysis($fundamentals, null, null, null, 'IDR');

        $this->assertTrue($result['horizonAlignment']['aligned']);
        $this->assertNull($result['horizonAlignment']['note']);
    }

    public function test_horizon_alignment_flags_conflict_between_trading_and_longterm_signals(): void
    {
        // Chronologically: price climbs 100 -> 179 over 80 days, then dips over the most recent 20
        // days down to 139. Long-term trend is clearly up, but the 20-day trading window is down.
        $pricesByAge = [];
        for ($t = 0; $t < 100; $t++) {
            $pricesByAge[$t] = $t <= 79 ? 100 + $t : 179 - ($t - 79) * 2;
        }
        $series = [];
        foreach (array_reverse($pricesByAge) as $close) {
            $series[] = ['close' => $close, 'volume' => 1000];
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertFalse($result['horizonAlignment']['aligned']);
        $this->assertStringContainsString('berlawanan arah dengan tren jangka panjang', $result['horizonAlignment']['note']);
    }

    public function test_contrarian_note_added_when_analyst_consensus_is_nearly_unanimous_buy(): void
    {
        $fundamentals = ['analystRatings' => ['strongBuy' => 8, 'buy' => 2, 'hold' => 0, 'sell' => 0, 'strongSell' => 0]];

        $result = $this->engine->buildAnalysis($fundamentals, null, null, null, 'USD');

        $this->assertStringContainsString('nyaris seragam ke arah Buy', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_contrarian_note_added_when_analyst_consensus_is_nearly_unanimous_sell(): void
    {
        $fundamentals = ['analystRatings' => ['strongBuy' => 0, 'buy' => 0, 'hold' => 0, 'sell' => 2, 'strongSell' => 8]];

        $result = $this->engine->buildAnalysis($fundamentals, null, null, null, 'USD');

        $this->assertStringContainsString('nyaris seragam ke arah Sell', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_contrarian_note_skipped_with_too_few_analysts(): void
    {
        $fundamentals = ['analystRatings' => ['strongBuy' => 4, 'buy' => 0, 'hold' => 0, 'sell' => 0, 'strongSell' => 0]];

        $result = $this->engine->buildAnalysis($fundamentals, null, null, null, 'USD');

        $this->assertStringNotContainsString('kontrarian', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_contrarian_note_skipped_when_consensus_is_only_moderately_one_sided(): void
    {
        $fundamentals = ['analystRatings' => ['strongBuy' => 5, 'buy' => 3, 'hold' => 2, 'sell' => 0, 'strongSell' => 0]];

        $result = $this->engine->buildAnalysis($fundamentals, null, null, null, 'USD');

        $this->assertStringNotContainsString('kontrarian', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_obv_confirms_the_move_when_volume_backs_a_sustained_uptrend(): void
    {
        $chrono = range(100, 124); // 25 closes, strictly rising
        $series = [];
        foreach (array_reverse($chrono) as $close) {
            $series[] = ['close' => $close, 'volume' => 1000];
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertStringContainsString('OBV (volume) ikut naik', implode(' ', $result['subScores']['momentum']['notes']));
    }

    public function test_obv_warns_when_price_rises_without_matching_volume_support(): void
    {
        // 19 down-days on heavy volume (OBV drags deeply negative), then one large up-day on
        // negligible volume — price nets higher overall, but the volume trail disagrees.
        $chrono = [200];
        for ($i = 1; $i <= 19; $i++) {
            $chrono[] = 200 - $i;
        }
        $chrono[] = end($chrono) + 150; // day 21: big jump up

        $volumes = [100_000];
        for ($i = 1; $i <= 19; $i++) {
            $volumes[] = 100_000;
        }
        $volumes[] = 1;

        $series = [];
        foreach (array_reverse(array_keys($chrono)) as $i) {
            $series[] = ['close' => $chrono[$i], 'volume' => $volumes[$i]];
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertStringContainsString('Divergence OBV', implode(' ', $result['subScores']['momentum']['notes']));
    }

    public function test_obv_signal_skipped_when_not_enough_history(): void
    {
        $chrono = range(100, 119); // exactly 20 closes, below OBV's 21-close minimum
        $series = [];
        foreach (array_reverse($chrono) as $close) {
            $series[] = ['close' => $close, 'volume' => 1000];
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertStringNotContainsString('OBV', implode(' ', $result['subScores']['momentum']['notes']));
    }

    public function test_sub_score_divergence_flagged_when_momentum_and_long_term_trend_disagree(): void
    {
        // Same fixture as the horizon-alignment conflict test: climbs 100->179 over 80 days, then
        // dips over the most recent 20 days down to 139 — momentum (trading) reads negative while
        // momentumLongTerm reads positive.
        $pricesByAge = [];
        for ($t = 0; $t < 100; $t++) {
            $pricesByAge[$t] = $t <= 79 ? 100 + $t : 179 - ($t - 79) * 2;
        }
        $series = [];
        foreach (array_reverse($pricesByAge) as $close) {
            $series[] = ['close' => $close, 'volume' => 1000];
        }

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertNotNull($result['subScoreDivergence']);
        $this->assertStringContainsString('Momentum', $result['subScoreDivergence']);
        $this->assertStringContainsString('Tren Panjang', $result['subScoreDivergence']);
    }

    public function test_sub_score_divergence_null_when_no_data_available(): void
    {
        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR');

        $this->assertNull($result['subScoreDivergence']);
    }

    public function test_stale_data_warning_flagged_when_latest_price_is_old(): void
    {
        $oldDate = date('Y-m-d', strtotime('-10 days'));
        $series = [['close' => 100, 'volume' => 1000, 'date' => $oldDate]];

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertNotNull($result['staleDataWarning']);
        $this->assertStringContainsString($oldDate, $result['staleDataWarning']);
    }

    public function test_stale_data_warning_null_when_price_is_recent(): void
    {
        $series = [['close' => 100, 'volume' => 1000, 'date' => date('Y-m-d')]];

        $result = $this->engine->buildAnalysis(null, null, $series, null, 'IDR');

        $this->assertNull($result['staleDataWarning']);
    }

    public function test_stale_data_warning_null_when_price_series_missing(): void
    {
        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR');

        $this->assertNull($result['staleDataWarning']);
    }

    public function test_market_regime_is_bull_when_benchmark_up_over_the_window(): void
    {
        $benchmark = array_fill(0, 21, ['close' => 1000, 'volume' => null]);
        $benchmark[0] = ['close' => 1100, 'volume' => null];

        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR', $benchmark, 'IHSG');

        $this->assertSame('bull', $result['marketRegime']);
    }

    public function test_market_regime_is_bear_when_benchmark_down_over_the_window(): void
    {
        $benchmark = array_fill(0, 21, ['close' => 1000, 'volume' => null]);
        $benchmark[0] = ['close' => 900, 'volume' => null];

        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR', $benchmark, 'IHSG');

        $this->assertSame('bear', $result['marketRegime']);
    }

    public function test_market_regime_is_sideways_when_benchmark_flat(): void
    {
        $benchmark = array_fill(0, 21, ['close' => 1000, 'volume' => null]);

        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR', $benchmark, 'IHSG');

        $this->assertSame('sideways', $result['marketRegime']);
    }

    public function test_market_regime_is_null_when_benchmark_series_missing(): void
    {
        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR');

        $this->assertNull($result['marketRegime']);
    }

    public function test_foreign_flow_scores_positive_when_net_buy(): void
    {
        $foreignFlow = ['buyValue' => 1_000_000_000, 'sellValue' => 200_000_000, 'asOfDate' => '2024-03-01'];

        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR', foreignFlow: $foreignFlow);

        $this->assertGreaterThan(0, $result['subScores']['foreignFlow']['score']);
        $this->assertStringContainsString('net beli', implode(' ', $result['subScores']['foreignFlow']['notes']));
        $this->assertStringContainsString('2024-03-01', implode(' ', $result['subScores']['foreignFlow']['notes']));
    }

    public function test_foreign_flow_scores_negative_when_net_sell(): void
    {
        $foreignFlow = ['buyValue' => 100_000_000, 'sellValue' => 900_000_000, 'asOfDate' => '2024-03-01'];

        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR', foreignFlow: $foreignFlow);

        $this->assertLessThan(0, $result['subScores']['foreignFlow']['score']);
        $this->assertStringContainsString('net jual', implode(' ', $result['subScores']['foreignFlow']['notes']));
    }

    public function test_foreign_flow_is_null_when_data_unavailable(): void
    {
        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR');

        $this->assertNull($result['subScores']['foreignFlow']['score']);
    }

    public function test_foreign_flow_is_null_when_no_transactions_recorded(): void
    {
        $foreignFlow = ['buyValue' => 0, 'sellValue' => 0, 'asOfDate' => '2024-03-01'];

        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR', foreignFlow: $foreignFlow);

        $this->assertNull($result['subScores']['foreignFlow']['score']);
    }

    public function test_foreign_flow_never_included_in_longterm_weights(): void
    {
        // A huge, purely one-day foreign flow shouldn't be able to move the multi-year call —
        // only 'trading' includes 'foreignFlow' in ScoringEngine::WEIGHTS.
        $foreignFlow = ['buyValue' => 1_000_000_000, 'sellValue' => 0, 'asOfDate' => '2024-03-01'];

        $withFlow = $this->engine->buildAnalysis(['pegRatio' => 0.8], null, null, null, 'IDR', foreignFlow: $foreignFlow);
        $withoutFlow = $this->engine->buildAnalysis(['pegRatio' => 0.8], null, null, null, 'IDR');

        $this->assertSame($withoutFlow['longterm']['score'], $withFlow['longterm']['score']);
    }

    public function test_commodity_context_note_is_folded_into_fundamentals_notes(): void
    {
        $result = $this->engine->buildAnalysis(
            ['pegRatio' => 0.8], null, null, null, 'IDR',
            commodityContextNote: 'Harga tembaga naik 12.0% dalam 3 bulan terakhir — contoh catatan.'
        );

        $this->assertStringContainsString('tembaga', implode(' ', $result['subScores']['fundamentals']['notes']));
    }

    public function test_corporate_action_note_for_upcoming_split(): void
    {
        $soon = date('Y-m-d', strtotime('+10 days'));

        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR', corporateAction: ['type' => 'split', 'date' => $soon]);

        $this->assertNotNull($result['corporateAction']);
        $this->assertStringContainsString('stock split', $result['corporateAction']);
    }

    public function test_corporate_action_note_for_upcoming_rights_issue(): void
    {
        $soon = date('Y-m-d', strtotime('+10 days'));

        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR', corporateAction: ['type' => 'rights_issue', 'date' => $soon]);

        $this->assertNotNull($result['corporateAction']);
        $this->assertStringContainsString('rights issue', $result['corporateAction']);
    }

    public function test_corporate_action_note_null_when_too_far_away(): void
    {
        $farAway = date('Y-m-d', strtotime('+90 days'));

        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR', corporateAction: ['type' => 'split', 'date' => $farAway]);

        $this->assertNull($result['corporateAction']);
    }

    public function test_corporate_action_note_null_when_missing(): void
    {
        $result = $this->engine->buildAnalysis(null, null, null, null, 'IDR');

        $this->assertNull($result['corporateAction']);
    }
}
