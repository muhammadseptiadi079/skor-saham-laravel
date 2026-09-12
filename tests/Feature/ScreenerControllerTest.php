<?php

namespace Tests\Feature;

use App\Models\AnalysisHistory;
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
}
