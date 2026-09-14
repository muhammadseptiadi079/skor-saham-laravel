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

    private function createGradedRow(string $subScore, float $score, bool $matches): void
    {
        AnalysisHistory::create([
            'ticker' => 'T'.uniqid(), 'market' => 'idx',
            'outcome_correct' => $matches, 'forward_return' => $matches ? 0.05 : -0.05,
            'sub_scores' => [$subScore => ['score' => $score]],
            'generated_at' => now(),
        ]);
    }

    public function test_no_weight_suggestion_below_minimum_sample_size(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createGradedRow('momentum', 0.5, false); // consistently wrong, but too few samples
        }

        $suggestions = collect(app(SubScoreAccuracyService::class)->weightSuggestions())->keyBy('subScore');

        $this->assertArrayNotHasKey('momentum', $suggestions);
    }

    public function test_suggests_lowering_weight_for_poor_directional_accuracy(): void
    {
        for ($i = 0; $i < 20; $i++) {
            // Score positive but price consistently moved the other way -> 0% accuracy.
            $this->createGradedRow('momentum', 0.5, false);
        }

        $suggestions = collect(app(SubScoreAccuracyService::class)->weightSuggestions())->keyBy('subScore');

        $this->assertArrayHasKey('momentum', $suggestions);
        $this->assertStringContainsString('turunkan bobotnya', $suggestions['momentum']['suggestion']);
    }

    public function test_suggests_raising_weight_for_strong_directional_accuracy(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->createGradedRow('fundamentals', 0.5, true); // always matched the actual direction
        }

        $suggestions = collect(app(SubScoreAccuracyService::class)->weightSuggestions())->keyBy('subScore');

        $this->assertArrayHasKey('fundamentals', $suggestions);
        $this->assertStringContainsString('dinaikkan bobotnya', $suggestions['fundamentals']['suggestion']);
    }

    public function test_no_suggestion_for_moderate_accuracy(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createGradedRow('ownership', 0.5, true);
        }
        for ($i = 0; $i < 10; $i++) {
            $this->createGradedRow('ownership', 0.5, false);
        }

        $suggestions = collect(app(SubScoreAccuracyService::class)->weightSuggestions())->keyBy('subScore');

        $this->assertArrayNotHasKey('ownership', $suggestions);
    }

    private function createConfidenceRow(string $confidence, bool $correct): void
    {
        AnalysisHistory::create([
            'ticker' => 'T'.uniqid(), 'market' => 'idx',
            'outcome_correct' => $correct, 'forward_return' => $correct ? 0.05 : -0.05,
            'trading_confidence' => $confidence,
            'generated_at' => now(),
        ]);
    }

    public function test_confidence_accuracy_reports_each_level_in_high_to_low_order(): void
    {
        $this->createConfidenceRow('Tinggi', true);
        $this->createConfidenceRow('Tinggi', true);
        $this->createConfidenceRow('Rendah', false);

        $report = app(SubScoreAccuracyService::class)->confidenceAccuracy();

        $this->assertSame(['Tinggi', 'Sedang', 'Rendah'], array_column($report, 'level'));
        $byLevel = collect($report)->keyBy('level');
        $this->assertSame(2, $byLevel['Tinggi']['sampleSize']);
        $this->assertEquals(100.0, $byLevel['Tinggi']['accuracy']);
        $this->assertSame(0, $byLevel['Sedang']['sampleSize']);
        $this->assertNull($byLevel['Sedang']['accuracy']);
    }

    public function test_no_calibration_suggestion_below_minimum_sample_size_per_level(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createConfidenceRow('Tinggi', true); // 100% accurate but too few samples
            $this->createConfidenceRow('Rendah', false); // 0% accurate but too few samples
        }

        $suggestion = app(SubScoreAccuracyService::class)->confidenceCalibrationSuggestion();

        $this->assertNull($suggestion);
    }

    public function test_no_calibration_suggestion_when_tinggi_is_meaningfully_more_accurate(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createConfidenceRow('Tinggi', true); // 100% accurate
            $this->createConfidenceRow('Rendah', $i < 5); // 33% accurate
        }

        $suggestion = app(SubScoreAccuracyService::class)->confidenceCalibrationSuggestion();

        $this->assertNull($suggestion);
    }

    public function test_calibration_suggestion_fires_when_tinggi_is_not_meaningfully_better_than_rendah(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createConfidenceRow('Tinggi', $i < 8); // ~53% accurate
            $this->createConfidenceRow('Rendah', $i < 7); // ~47% accurate — barely different
        }

        $suggestion = app(SubScoreAccuracyService::class)->confidenceCalibrationSuggestion();

        $this->assertNotNull($suggestion);
        $this->assertStringContainsString('confidenceFor()', $suggestion['suggestion']);
    }

    public function test_calibration_suggestion_fires_when_tinggi_is_actually_worse_than_rendah(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createConfidenceRow('Tinggi', $i < 3); // 20% accurate — inverted!
            $this->createConfidenceRow('Rendah', $i < 12); // 80% accurate
        }

        $suggestion = app(SubScoreAccuracyService::class)->confidenceCalibrationSuggestion();

        $this->assertNotNull($suggestion);
        $this->assertStringContainsString('malah lebih rendah', $suggestion['suggestion']);
    }
}
