<?php

namespace Tests\Feature;

use App\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchlistControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_list_and_remove_a_watchlist_item(): void
    {
        $add = $this->postJson('/api/watchlist', ['ticker' => 'bbca', 'market' => 'idx', 'name' => 'Bank Central Asia']);
        $add->assertCreated();
        $add->assertJsonPath('ticker', 'BBCA');

        $list = $this->getJson('/api/watchlist');
        $list->assertOk();
        $list->assertJsonCount(1);

        $id = $add->json('id');
        $this->deleteJson("/api/watchlist/{$id}")->assertOk();
        $this->getJson('/api/watchlist')->assertJsonCount(0);
    }

    public function test_adding_the_same_ticker_twice_does_not_duplicate(): void
    {
        WatchlistItem::create(['ticker' => 'AAPL', 'market' => 'global']);

        $this->postJson('/api/watchlist', ['ticker' => 'aapl', 'market' => 'global'])->assertCreated();

        $this->assertDatabaseCount('watchlist_items', 1);
    }

    public function test_rejects_invalid_market(): void
    {
        $this->postJson('/api/watchlist', ['ticker' => 'AAPL', 'market' => 'moon'])
            ->assertStatus(422);
    }
}
