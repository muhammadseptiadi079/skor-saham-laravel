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
}
