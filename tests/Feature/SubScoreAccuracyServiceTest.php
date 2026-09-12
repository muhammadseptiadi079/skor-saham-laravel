<?php

namespace Tests\Feature;

use App\Models\AnalysisHistory;
use App\Services\SubScoreAccuracyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubScoreAccuracyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_directional_accuracy_per_sub_score(): void
    {
        // Row 1: fundamentals positive, price went up -> match.
        AnalysisHistory::create([
            'ticker' => 'A', 'market' => 'idx', 'outcome_correct' => true, 'forward_return' => 0.10,
            'sub_scores' => ['fundamentals' => ['score' => 0.5], 'news' => ['score' => -0.3]],
            'generated_at' => now(),
        ]);
        // Row 2: fundamentals positive again, price went down -> mismatch.
        AnalysisHistory::create([
            'ticker' => 'B', 'market' => 'idx', 'outcome_correct' => false, 'forward_return' => -0.05,
            'sub_scores' => ['fundamentals' => ['score' => 0.4], 'news' => ['score' => -0.2]],
            'generated_at' => now(),
        ]);
        // Ungraded row must be excluded entirely.
        AnalysisHistory::create([
            'ticker' => 'C', 'market' => 'idx', 'outcome_correct' => null, 'forward_return' => null,
            'sub_scores' => ['fundamentals' => ['score' => 0.9]],
            'generated_at' => now(),
        ]);

        $report = collect(app(SubScoreAccuracyService::class)->report())->keyBy('subScore');

        // fundamentals: 1/2 matched the actual direction.
        $this->assertSame(2, $report['fundamentals']['sampleSize']);
        $this->assertEquals(50.0, $report['fundamentals']['directionalAccuracy']);

        // news: negative both times, price went up then down -> 1/2 matched too.
        $this->assertSame(2, $report['news']['sampleSize']);
        $this->assertEquals(50.0, $report['news']['directionalAccuracy']);

        // momentum was never present in either row's sub_scores.
        $this->assertSame(0, $report['momentum']['sampleSize']);
        $this->assertNull($report['momentum']['directionalAccuracy']);
    }
}
