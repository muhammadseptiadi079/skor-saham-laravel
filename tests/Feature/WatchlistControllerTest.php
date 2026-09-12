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
        $add->assertJsonPath('sector', 'Lainnya');
        $add->assertJsonPath('is_favorite', false);

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

    public function test_can_set_sector_when_adding(): void
    {
        $add = $this->postJson('/api/watchlist', [
            'ticker' => 'ANTM',
            'market' => 'idx',
            'sector' => 'Pertambangan',
        ]);

        $add->assertCreated();
        $add->assertJsonPath('sector', 'Pertambangan');
    }

    public function test_rejects_invalid_sector(): void
    {
        $this->postJson('/api/watchlist', ['ticker' => 'ANTM', 'market' => 'idx', 'sector' => 'Bukan Sektor'])
            ->assertStatus(422);
    }

    public function test_can_reassign_sector_via_update(): void
    {
        $item = WatchlistItem::create(['ticker' => 'BBRI', 'market' => 'idx', 'sector' => 'Lainnya']);

        $update = $this->patchJson("/api/watchlist/{$item->id}", ['sector' => 'Keuangan & Perbankan']);

        $update->assertOk();
        $update->assertJsonPath('sector', 'Keuangan & Perbankan');
        $this->assertSame('Keuangan & Perbankan', $item->fresh()->sector);
    }

    public function test_can_toggle_favorite_via_update(): void
    {
        $item = WatchlistItem::create(['ticker' => 'BMRI', 'market' => 'idx']);

        $update = $this->patchJson("/api/watchlist/{$item->id}", ['is_favorite' => true]);

        $update->assertOk();
        $update->assertJsonPath('is_favorite', true);
        $this->assertTrue($item->fresh()->is_favorite);
    }

    public function test_starter_pack_bulk_adds_curated_stocks_for_sector(): void
    {
        $response = $this->postJson('/api/watchlist/starter-pack', ['sector' => 'Pertambangan']);

        $response->assertOk();
        $added = $response->json('added');
        $this->assertGreaterThan(5, count($added));
        $this->assertSame([], $response->json('skipped'));
        $this->assertDatabaseHas('watchlist_items', ['ticker' => 'ANTM', 'market' => 'idx', 'sector' => 'Pertambangan']);
    }

    public function test_starter_pack_skips_tickers_already_in_watchlist_without_changing_their_sector(): void
    {
        WatchlistItem::create(['ticker' => 'ANTM', 'market' => 'idx', 'sector' => 'Lainnya', 'is_favorite' => true]);

        $response = $this->postJson('/api/watchlist/starter-pack', ['sector' => 'Pertambangan']);

        $response->assertOk();
        $this->assertContains('ANTM', $response->json('skipped'));
        $this->assertDatabaseHas('watchlist_items', ['ticker' => 'ANTM', 'sector' => 'Lainnya', 'is_favorite' => true]);
    }

    public function test_starter_pack_rejects_sector_without_a_curated_list(): void
    {
        $this->postJson('/api/watchlist/starter-pack', ['sector' => 'Lainnya'])->assertStatus(422);
        $this->postJson('/api/watchlist/starter-pack', ['sector' => 'Bukan Sektor'])->assertStatus(422);
    }
}
