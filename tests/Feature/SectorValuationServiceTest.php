<?php

namespace Tests\Feature;

use App\Models\AnalysisHistory;
use App\Models\WatchlistItem;
use App\Services\SectorValuationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectorValuationServiceTest extends TestCase
{
    use RefreshDatabase;

    private SectorValuationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SectorValuationService::class);
    }

    private function history(string $ticker, ?float $pe, ?float $peg = null): void
    {
        AnalysisHistory::create([
            'ticker' => $ticker,
            'market' => 'idx',
            'generated_at' => now(),
            'pe_ratio' => $pe,
            'peg_ratio' => $peg,
        ]);
    }

    public function test_returns_null_when_ticker_not_in_watchlist(): void
    {
        $this->assertNull($this->service->averagesFor('BBCA', 'idx'));
    }

    public function test_returns_null_when_ticker_sector_is_default(): void
    {
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'sector' => 'Lainnya']);

        $this->assertNull($this->service->averagesFor('BBCA', 'idx'));
    }

    public function test_returns_null_when_fewer_than_two_peers_in_sector(): void
    {
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'sector' => 'Keuangan & Perbankan']);
        WatchlistItem::create(['ticker' => 'BMRI', 'market' => 'idx', 'sector' => 'Keuangan & Perbankan']);

        $this->assertNull($this->service->averagesFor('BBCA', 'idx'));
    }

    public function test_averages_pe_and_peg_across_sector_peers_excluding_self(): void
    {
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'sector' => 'Keuangan & Perbankan']);
        WatchlistItem::create(['ticker' => 'BMRI', 'market' => 'idx', 'sector' => 'Keuangan & Perbankan']);
        WatchlistItem::create(['ticker' => 'BBRI', 'market' => 'idx', 'sector' => 'Keuangan & Perbankan']);
        WatchlistItem::create(['ticker' => 'ANTM', 'market' => 'idx', 'sector' => 'Pertambangan']);

        $this->history('BBCA', 5.0); // self — must be excluded from the average
        $this->history('BMRI', 18.0, 1.5);
        $this->history('BBRI', 22.0, 2.5);
        $this->history('ANTM', 100.0); // different sector — must be excluded

        $result = $this->service->averagesFor('BBCA', 'idx');

        $this->assertNotNull($result);
        $this->assertSame('Keuangan & Perbankan', $result['sector']);
        $this->assertEqualsWithDelta(20.0, $result['avgPe'], 0.0001);
        $this->assertSame(2, $result['peSampleSize']);
        $this->assertEqualsWithDelta(2.0, $result['avgPeg'], 0.0001);
    }

    public function test_uses_most_recent_history_row_per_peer_ticker(): void
    {
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'sector' => 'Keuangan & Perbankan']);
        WatchlistItem::create(['ticker' => 'BMRI', 'market' => 'idx', 'sector' => 'Keuangan & Perbankan']);
        WatchlistItem::create(['ticker' => 'BBRI', 'market' => 'idx', 'sector' => 'Keuangan & Perbankan']);

        AnalysisHistory::create(['ticker' => 'BMRI', 'market' => 'idx', 'generated_at' => now()->subDays(10), 'pe_ratio' => 999.0]);
        AnalysisHistory::create(['ticker' => 'BMRI', 'market' => 'idx', 'generated_at' => now(), 'pe_ratio' => 18.0]);
        $this->history('BBRI', 22.0);

        $result = $this->service->averagesFor('BBCA', 'idx');

        $this->assertEqualsWithDelta(20.0, $result['avgPe'], 0.0001);
    }
}
