<?php

namespace Tests\Feature;

use App\Models\AnalysisHistory;
use App\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccuracyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_summary_when_nothing_graded_yet(): void
    {
        $response = $this->getJson('/api/accuracy');

        $response->assertOk();
        $response->assertJson(['sampleSize' => 0, 'accuracy' => null, 'byLabel' => []]);
        $response->assertJsonStructure(['subScoreAccuracy' => [['subScore', 'sampleSize', 'directionalAccuracy']]]);
    }

    public function test_aggregates_accuracy_by_label(): void
    {
        AnalysisHistory::create([
            'ticker' => 'A', 'market' => 'idx', 'trading_label' => 'Buy',
            'outcome_correct' => true, 'forward_return' => 0.08, 'generated_at' => now(),
        ]);
        AnalysisHistory::create([
            'ticker' => 'B', 'market' => 'idx', 'trading_label' => 'Buy',
            'outcome_correct' => false, 'forward_return' => -0.02, 'generated_at' => now(),
        ]);
        AnalysisHistory::create([
            'ticker' => 'C', 'market' => 'idx', 'trading_label' => 'Sell',
            'outcome_correct' => true, 'forward_return' => -0.05, 'generated_at' => now(),
        ]);
        // Ungraded rows (still awaiting evaluation) should never be counted.
        AnalysisHistory::create([
            'ticker' => 'D', 'market' => 'idx', 'trading_label' => 'Buy',
            'outcome_correct' => null, 'forward_return' => null, 'generated_at' => now(),
        ]);

        $response = $this->getJson('/api/accuracy');

        $response->assertOk();
        $response->assertJsonPath('sampleSize', 3);
        $response->assertJsonPath('accuracy', 66.7);

        $byLabel = collect($response->json('byLabel'))->keyBy('label');
        $this->assertSame(2, $byLabel['Buy']['sampleSize']);
        $this->assertSame(1, $byLabel['Buy']['correct']);
        $this->assertEquals(50.0, $byLabel['Buy']['accuracy']);
        $this->assertSame(1, $byLabel['Sell']['sampleSize']);
        $this->assertEquals(100.0, $byLabel['Sell']['accuracy']);
    }

    public function test_aggregates_accuracy_by_market_regime(): void
    {
        AnalysisHistory::create([
            'ticker' => 'A', 'market' => 'idx', 'trading_label' => 'Buy',
            'outcome_correct' => true, 'forward_return' => 0.08, 'market_regime' => 'bull', 'generated_at' => now(),
        ]);
        AnalysisHistory::create([
            'ticker' => 'B', 'market' => 'idx', 'trading_label' => 'Buy',
            'outcome_correct' => true, 'forward_return' => 0.03, 'market_regime' => 'bull', 'generated_at' => now(),
        ]);
        AnalysisHistory::create([
            'ticker' => 'C', 'market' => 'idx', 'trading_label' => 'Buy',
            'outcome_correct' => false, 'forward_return' => -0.02, 'market_regime' => 'bear', 'generated_at' => now(),
        ]);
        // Graded before market_regime existed -> excluded from this breakdown, still counts overall.
        AnalysisHistory::create([
            'ticker' => 'D', 'market' => 'idx', 'trading_label' => 'Buy',
            'outcome_correct' => true, 'forward_return' => 0.01, 'market_regime' => null, 'generated_at' => now(),
        ]);

        $response = $this->getJson('/api/accuracy');

        $response->assertOk();
        $response->assertJsonPath('sampleSize', 4);

        $byRegime = collect($response->json('byMarketRegime'))->keyBy('label');
        $this->assertSame(2, $byRegime['Pasar Naik']['sampleSize']);
        $this->assertEquals(100.0, $byRegime['Pasar Naik']['accuracy']);
        $this->assertSame(1, $byRegime['Pasar Turun']['sampleSize']);
        $this->assertEquals(0.0, $byRegime['Pasar Turun']['accuracy']);
        $this->assertCount(2, $byRegime); // the null-regime row must not appear as a group
    }

    public function test_aggregates_accuracy_by_watchlist_sector(): void
    {
        WatchlistItem::create(['ticker' => 'ANTM', 'market' => 'idx', 'sector' => 'Pertambangan']);
        WatchlistItem::create(['ticker' => 'PTBA', 'market' => 'idx', 'sector' => 'Pertambangan']);
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'sector' => 'Lainnya']);

        AnalysisHistory::create([
            'ticker' => 'ANTM', 'market' => 'idx', 'trading_label' => 'Buy',
            'outcome_correct' => true, 'forward_return' => 0.08, 'generated_at' => now(),
        ]);
        AnalysisHistory::create([
            'ticker' => 'PTBA', 'market' => 'idx', 'trading_label' => 'Buy',
            'outcome_correct' => false, 'forward_return' => -0.02, 'generated_at' => now(),
        ]);
        // Sector "Lainnya" (no real sector assigned) must be excluded from the breakdown.
        AnalysisHistory::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'trading_label' => 'Buy',
            'outcome_correct' => true, 'forward_return' => 0.03, 'generated_at' => now(),
        ]);
        // Not in the watchlist at all -> also excluded, but still counted in overall sampleSize.
        AnalysisHistory::create([
            'ticker' => 'ZZZZ', 'market' => 'idx', 'trading_label' => 'Buy',
            'outcome_correct' => true, 'forward_return' => 0.01, 'generated_at' => now(),
        ]);

        $response = $this->getJson('/api/accuracy');

        $response->assertOk();
        $response->assertJsonPath('sampleSize', 4);

        $bySector = collect($response->json('byWatchlistSector'))->keyBy('label');
        $this->assertSame(2, $bySector['Pertambangan']['sampleSize']);
        $this->assertEquals(50.0, $bySector['Pertambangan']['accuracy']);
        $this->assertCount(1, $bySector); // "Lainnya" and the untracked ticker must not appear
    }

    public function test_filters_by_market(): void
    {
        AnalysisHistory::create([
            'ticker' => 'A', 'market' => 'idx', 'trading_label' => 'Buy',
            'outcome_correct' => true, 'forward_return' => 0.05, 'generated_at' => now(),
        ]);
        AnalysisHistory::create([
            'ticker' => 'AAPL', 'market' => 'global', 'trading_label' => 'Sell',
            'outcome_correct' => false, 'forward_return' => 0.05, 'generated_at' => now(),
        ]);

        $response = $this->getJson('/api/accuracy?market=idx');

        $response->assertJsonPath('sampleSize', 1);
    }
}
