<?php

namespace Tests\Feature;

use App\Models\AnalysisHistory;
use App\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_screener_returns_only_latest_score_per_ticker_above_threshold(): void
    {
        // Stale, weaker score for BBCA (should be superseded by the newer row below).
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'name' => 'BCA', 'currency' => 'IDR',
            'trading_score' => 0.05, 'trading_label' => 'Hold / Netral',
            'longterm_score' => 0.05, 'longterm_label' => 'Hold / Netral',
            'generated_at' => now()->subDay(),
        ]);
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'name' => 'BCA', 'currency' => 'IDR',
            'trading_score' => 0.6, 'trading_label' => 'Strong Buy',
            'longterm_score' => 0.6, 'longterm_label' => 'Strong Buy',
            'generated_at' => now(),
        ]);
        // Below the gainer threshold -> excluded.
        AnalysisHistory::create([
            'ticker' => 'TLKM', 'market' => 'idx', 'name' => 'Telkom', 'currency' => 'IDR',
            'trading_score' => -0.2, 'trading_label' => 'Sell',
            'longterm_score' => -0.2, 'longterm_label' => 'Sell',
            'generated_at' => now(),
        ]);
        // Different market -> excluded from an idx query.
        AnalysisHistory::create([
            'ticker' => 'AAPL', 'market' => 'global', 'name' => 'Apple', 'currency' => 'USD',
            'trading_score' => 0.8, 'trading_label' => 'Strong Buy',
            'longterm_score' => 0.8, 'longterm_label' => 'Strong Buy',
            'generated_at' => now(),
        ]);

        $response = $this->getJson('/api/screener?market=idx');

        $response->assertOk();
        $items = $response->json('items');
        $this->assertCount(1, $items);
        $this->assertSame('BBCA', $items[0]['ticker']);
        $this->assertSame(0.6, $items[0]['trading_score']);
    }

    public function test_screener_uses_watchlist_sector_when_the_user_has_classified_it(): void
    {
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'sector' => 'Keuangan & Perbankan']);
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'name' => 'BCA', 'currency' => 'IDR',
            'trading_score' => 0.6, 'trading_label' => 'Strong Buy',
            'generated_at' => now(),
        ]);

        $items = $this->getJson('/api/screener?market=idx')->json('items');

        $this->assertSame('Keuangan & Perbankan', $items[0]['sector']);
    }

    public function test_screener_falls_back_to_starter_pack_sector_when_not_in_watchlist(): void
    {
        // ADRO isn't in the watchlist, but is a known ticker in SectorStarterPacks' Pertambangan pack.
        AnalysisHistory::create([
            'ticker' => 'ADRO', 'market' => 'idx', 'name' => 'Adaro', 'currency' => 'IDR',
            'trading_score' => 0.6, 'trading_label' => 'Strong Buy',
            'generated_at' => now(),
        ]);

        $items = $this->getJson('/api/screener?market=idx')->json('items');

        $this->assertSame('Pertambangan', $items[0]['sector']);
    }

    public function test_screener_defaults_to_lainnya_for_an_unrecognized_ticker(): void
    {
        AnalysisHistory::create([
            'ticker' => 'ZZZZ', 'market' => 'idx', 'name' => 'Unknown Co', 'currency' => 'IDR',
            'trading_score' => 0.6, 'trading_label' => 'Strong Buy',
            'generated_at' => now(),
        ]);

        $items = $this->getJson('/api/screener?market=idx')->json('items');

        $this->assertSame('Lainnya', $items[0]['sector']);
    }

    public function test_screener_attaches_avg_forward_return_for_trading_horizon_with_enough_samples(): void
    {
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'name' => 'BCA', 'currency' => 'IDR',
            'trading_score' => 0.6, 'trading_label' => 'Strong Buy',
            'generated_at' => now(),
        ]);
        // 10 graded "Strong Buy" rows (a different ticker each, all already evaluated), averaging
        // +5% forward return.
        for ($i = 0; $i < 10; $i++) {
            AnalysisHistory::create([
                'ticker' => "GRD{$i}", 'market' => 'idx', 'name' => 'Graded', 'currency' => 'IDR',
                'trading_score' => 0.6, 'trading_label' => 'Strong Buy',
                'generated_at' => now()->subDays(40),
                'outcome_correct' => true, 'forward_return' => 0.05,
            ]);
        }

        $items = $this->getJson('/api/screener?market=idx')->json('items');
        $bbca = collect($items)->firstWhere('ticker', 'BBCA');

        $this->assertEquals(5.0, $bbca['avgForwardReturnPct']);
    }

    public function test_screener_omits_avg_forward_return_below_the_sample_threshold(): void
    {
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'name' => 'BCA', 'currency' => 'IDR',
            'trading_score' => 0.6, 'trading_label' => 'Strong Buy',
            'generated_at' => now(),
        ]);
        // Only 3 graded rows — below MIN_SAMPLE_FOR_RETURN (10).
        for ($i = 0; $i < 3; $i++) {
            AnalysisHistory::create([
                'ticker' => "GRD{$i}", 'market' => 'idx', 'name' => 'Graded', 'currency' => 'IDR',
                'trading_score' => 0.6, 'trading_label' => 'Strong Buy',
                'generated_at' => now()->subDays(40),
                'outcome_correct' => true, 'forward_return' => 0.05,
            ]);
        }

        $items = $this->getJson('/api/screener?market=idx')->json('items');
        $bbca = collect($items)->firstWhere('ticker', 'BBCA');

        $this->assertNull($bbca['avgForwardReturnPct']);
    }

    public function test_screener_never_attaches_avg_forward_return_for_longterm_horizon(): void
    {
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'name' => 'BCA', 'currency' => 'IDR',
            'longterm_score' => 0.6, 'longterm_label' => 'Strong Buy',
            'generated_at' => now(),
        ]);
        for ($i = 0; $i < 10; $i++) {
            AnalysisHistory::create([
                'ticker' => "GRD{$i}", 'market' => 'idx', 'name' => 'Graded', 'currency' => 'IDR',
                'trading_score' => 0.6, 'trading_label' => 'Strong Buy',
                'generated_at' => now()->subDays(40),
                'outcome_correct' => true, 'forward_return' => 0.05,
            ]);
        }

        $items = $this->getJson('/api/screener?market=idx&horizon=longterm')->json('items');
        $bbca = collect($items)->firstWhere('ticker', 'BBCA');

        $this->assertNull($bbca['avgForwardReturnPct']);
    }
}
