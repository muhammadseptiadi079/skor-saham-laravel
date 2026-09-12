<?php

namespace Tests\Feature;

use App\Models\AnalysisHistory;
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
