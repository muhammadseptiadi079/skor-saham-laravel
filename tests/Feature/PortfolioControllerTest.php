<?php

namespace Tests\Feature;

use App\Models\AnalysisHistory;
use App\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_when_no_holdings(): void
    {
        $response = $this->getJson('/api/portfolio');

        $response->assertOk();
        $response->assertJson(['holdings' => [], 'summaries' => []]);
    }

    public function test_ignores_favorites_without_shares_or_price_set(): void
    {
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'is_favorite' => true]);
        WatchlistItem::create(['ticker' => 'ANTM', 'market' => 'idx', 'is_favorite' => true, 'shares_owned' => 100]);
        WatchlistItem::create(['ticker' => 'TLKM', 'market' => 'idx', 'is_favorite' => false, 'shares_owned' => 100, 'avg_buy_price' => 3000]);

        $response = $this->getJson('/api/portfolio');

        $response->assertJsonCount(0, 'holdings');
    }

    public function test_computes_unrealized_pnl_against_the_latest_cached_price(): void
    {
        WatchlistItem::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'is_favorite' => true,
            'shares_owned' => 100, 'avg_buy_price' => 9000,
        ]);
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'generated_at' => now()->subDays(2), 'price_at_generation' => 8500,
        ]);
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'generated_at' => now(), 'price_at_generation' => 10000,
        ]);

        $response = $this->getJson('/api/portfolio');

        $response->assertOk();
        $holding = $response->json('holdings.0');
        $this->assertSame('BBCA', $holding['ticker']);
        $this->assertEquals(10000, $holding['currentPrice']); // most recent row, not the older one
        $this->assertEquals(900000, $holding['costBasis']); // 100 * 9000
        $this->assertEquals(1000000, $holding['currentValue']); // 100 * 10000
        $this->assertEquals(100000, $holding['unrealizedPnl']);
        $this->assertEqualsWithDelta(0.1111, $holding['unrealizedPnlPct'], 0.001);
    }

    public function test_current_price_null_when_no_cached_analysis_yet(): void
    {
        WatchlistItem::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'is_favorite' => true,
            'shares_owned' => 100, 'avg_buy_price' => 9000,
        ]);

        $response = $this->getJson('/api/portfolio');

        $holding = $response->json('holdings.0');
        $this->assertNull($holding['currentPrice']);
        $this->assertNull($holding['currentValue']);
        $this->assertNull($holding['unrealizedPnl']);
        $this->assertEquals(900000, $holding['costBasis']); // still known regardless of price
    }

    public function test_summaries_are_grouped_and_never_mixed_across_currencies(): void
    {
        WatchlistItem::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'is_favorite' => true,
            'shares_owned' => 100, 'avg_buy_price' => 9000,
        ]);
        AnalysisHistory::create(['ticker' => 'BBCA', 'market' => 'idx', 'generated_at' => now(), 'price_at_generation' => 10000]);

        WatchlistItem::create([
            'ticker' => 'AAPL', 'market' => 'global', 'is_favorite' => true,
            'shares_owned' => 10, 'avg_buy_price' => 150,
        ]);
        AnalysisHistory::create(['ticker' => 'AAPL', 'market' => 'global', 'generated_at' => now(), 'price_at_generation' => 180]);

        $response = $this->getJson('/api/portfolio');

        $summaries = collect($response->json('summaries'))->keyBy('market');
        $this->assertSame('IDR', $summaries['idx']['currency']);
        $this->assertEquals(1000000, $summaries['idx']['totalCurrentValue']);
        $this->assertSame('USD', $summaries['global']['currency']);
        $this->assertEquals(1800, $summaries['global']['totalCurrentValue']);
    }

    public function test_total_cost_basis_counts_unpriced_holdings_but_pnl_does_not(): void
    {
        WatchlistItem::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'is_favorite' => true,
            'shares_owned' => 100, 'avg_buy_price' => 9000,
        ]);
        AnalysisHistory::create(['ticker' => 'BBCA', 'market' => 'idx', 'generated_at' => now(), 'price_at_generation' => 10000]);

        // No cached price for this one at all.
        WatchlistItem::create([
            'ticker' => 'ANTM', 'market' => 'idx', 'is_favorite' => true,
            'shares_owned' => 50, 'avg_buy_price' => 2000,
        ]);

        $response = $this->getJson('/api/portfolio');

        $summary = $response->json('summaries.0');
        $this->assertSame(2, $summary['holdingsCount']);
        $this->assertSame(1, $summary['pricedHoldingsCount']);
        $this->assertEquals(900000 + 100000, $summary['totalCostBasis']); // both holdings' full cost
        $this->assertEquals(100000, $summary['totalUnrealizedPnl']); // only the priced one
    }

    public function test_filters_by_market(): void
    {
        WatchlistItem::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'is_favorite' => true,
            'shares_owned' => 100, 'avg_buy_price' => 9000,
        ]);
        WatchlistItem::create([
            'ticker' => 'AAPL', 'market' => 'global', 'is_favorite' => true,
            'shares_owned' => 10, 'avg_buy_price' => 150,
        ]);

        $response = $this->getJson('/api/portfolio?market=global');

        $response->assertJsonCount(1, 'holdings');
        $response->assertJsonPath('holdings.0.ticker', 'AAPL');
    }
}
