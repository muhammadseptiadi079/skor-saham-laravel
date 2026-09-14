<?php

namespace Tests\Feature;

use App\Models\StockUniverseItem;
use App\Models\WatchlistItem;
use App\Services\TickerDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TickerDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TickerDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TickerDetectionService;
    }

    public function test_detects_a_watchlisted_ticker_mentioned_in_uppercase(): void
    {
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'name' => 'Bank Central Asia Tbk']);

        $matches = $this->service->detect('BBCA melaporkan laba bersih naik 20% tahun ini.');

        $this->assertCount(1, $matches);
        $this->assertSame('BBCA', $matches[0]['ticker']);
        $this->assertSame('idx', $matches[0]['market']);
    }

    public function test_does_not_match_a_ticker_word_written_in_lowercase(): void
    {
        // GOTO is also an ordinary English phrase in lowercase — case-sensitivity here is what
        // keeps "goto" in plain text from being misread as a stock mention.
        WatchlistItem::create(['ticker' => 'GOTO', 'market' => 'idx', 'name' => 'GoTo Gojek Tokopedia Tbk']);

        $matches = $this->service->detect('Jangan lupa goto halaman berikutnya untuk baca selengkapnya.');

        $this->assertCount(0, $matches);
    }

    public function test_detects_by_company_name_with_legal_suffix_stripped(): void
    {
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'name' => 'Bank Central Asia Tbk']);

        $matches = $this->service->detect('Bank Central Asia mencatat pertumbuhan kredit yang solid.');

        $this->assertCount(1, $matches);
        $this->assertSame('BBCA', $matches[0]['ticker']);
    }

    public function test_returns_multiple_candidates_when_more_than_one_stock_is_mentioned(): void
    {
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'name' => 'Bank Central Asia Tbk']);
        WatchlistItem::create(['ticker' => 'BBRI', 'market' => 'idx', 'name' => 'Bank Rakyat Indonesia Tbk']);

        $matches = $this->service->detect('Saham perbankan BBCA dan BBRI kompak menguat hari ini.');

        $tickers = array_column($matches, 'ticker');
        $this->assertContains('BBCA', $tickers);
        $this->assertContains('BBRI', $tickers);
    }

    public function test_returns_no_candidates_for_unrelated_text(): void
    {
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'name' => 'Bank Central Asia Tbk']);

        $matches = $this->service->detect('Cuaca hari ini cerah berawan di seluruh wilayah Jakarta.');

        $this->assertCount(0, $matches);
    }

    public function test_falls_back_to_sector_starter_pack_names_when_not_watchlisted(): void
    {
        // ANTM isn't in the watchlist or stock universe at all here — only in the static starter
        // pack list — so this proves that fallback source actually gets consulted.
        $matches = $this->service->detect('Aneka Tambang mengumumkan ekspansi tambang baru di Sulawesi.');

        $tickers = array_column($matches, 'ticker');
        $this->assertContains('ANTM', $tickers);
    }

    public function test_stock_universe_ticker_without_a_name_is_still_matched_by_ticker_code(): void
    {
        StockUniverseItem::create(['ticker' => 'ASII', 'market' => 'idx', 'name' => null, 'category' => 'lq45']);

        $matches = $this->service->detect('ASII membukukan penjualan otomotif yang kuat kuartal ini.');

        $tickers = array_column($matches, 'ticker');
        $this->assertContains('ASII', $tickers);
    }

    public function test_watchlist_name_takes_priority_over_starter_pack_name(): void
    {
        // Deliberately different from the real starter-pack name, just to prove the watchlist's own
        // (user/API-sourced) name wins when both sources know about the same ticker.
        WatchlistItem::create(['ticker' => 'ANTM', 'market' => 'idx', 'name' => 'PT Aneka Tambang Custom Name']);

        $matches = $this->service->detect('ANTM naik tipis hari ini.');

        $match = collect($matches)->firstWhere('ticker', 'ANTM');
        $this->assertSame('PT Aneka Tambang Custom Name', $match['name']);
    }
}
