<?php

namespace Tests\Feature;

use App\Models\AnalysisHistory;
use App\Services\NewsVolumeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsVolumeServiceTest extends TestCase
{
    use RefreshDatabase;

    private NewsVolumeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NewsVolumeService::class);
    }

    private function history(string $ticker, int $count, int $daysAgo = 1): void
    {
        AnalysisHistory::create([
            'ticker' => $ticker,
            'market' => 'idx',
            'generated_at' => now()->subDays($daysAgo),
            'news_article_count' => $count,
        ]);
    }

    public function test_returns_null_when_current_count_is_too_low(): void
    {
        $this->history('BBCA', 2, 1);
        $this->history('BBCA', 3, 2);
        $this->history('BBCA', 2, 3);

        $this->assertNull($this->service->spikeNoteFor('BBCA', 'idx', 4));
    }

    public function test_returns_null_when_not_enough_history_yet(): void
    {
        $this->history('BBCA', 2, 1);

        $this->assertNull($this->service->spikeNoteFor('BBCA', 'idx', 20));
    }

    public function test_returns_null_when_current_count_is_not_a_meaningful_spike(): void
    {
        $this->history('BBCA', 5, 1);
        $this->history('BBCA', 6, 2);
        $this->history('BBCA', 4, 3);

        // Baseline ~5, current 8 is not >= 2x baseline.
        $this->assertNull($this->service->spikeNoteFor('BBCA', 'idx', 8));
    }

    public function test_flags_spike_when_current_count_is_well_above_baseline(): void
    {
        $this->history('BBCA', 3, 1);
        $this->history('BBCA', 4, 2);
        $this->history('BBCA', 2, 3);

        $note = $this->service->spikeNoteFor('BBCA', 'idx', 20);

        $this->assertNotNull($note);
        $this->assertStringContainsString('20 artikel', $note);
        $this->assertStringContainsString('sorotan', $note);
    }

    public function test_only_compares_against_the_same_ticker_and_market(): void
    {
        $this->history('AAPL', 3, 1); // different ticker, ignored
        $this->history('BBCA', 3, 1);
        $this->history('BBCA', 4, 2);
        $this->history('BBCA', 2, 3);

        $note = $this->service->spikeNoteFor('BBCA', 'idx', 20);

        $this->assertNotNull($note);
    }
}
