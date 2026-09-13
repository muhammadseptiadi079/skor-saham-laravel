<?php

namespace App\Services;

use App\Models\AnalysisHistory;

// Diagnostic report: of the backtest-graded rows, how often did each individual sub-score's sign
// actually agree with the direction the price ended up moving? This is deliberately just a
// report for a human to read — it does NOT auto-rewrite ScoringEngine::WEIGHTS. Sample sizes are
// far too small early on (and will stay modest for a personal-use app) to safely auto-tune
// weights without mistaking noise for signal; a person deciding "this sub-score has looked weak
// for months, let's lower its weight" is safer than a formula doing it from a handful of rows.
class SubScoreAccuracyService
{
    private const SUB_SCORE_KEYS = ['fundamentals', 'news', 'momentum', 'momentumLongTerm', 'ownership'];

    // A sub-score needs at least this many graded samples before its accuracy is trusted enough to
    // even suggest a weight change — below this, a run of luck or bad luck is too likely to be
    // mistaken for a real pattern.
    private const MIN_SAMPLE_FOR_SUGGESTION = 20;

    private const LOW_ACCURACY_THRESHOLD = 45.0;

    private const HIGH_ACCURACY_THRESHOLD = 65.0;

    /** @return array<int, array{subScore: string, sampleSize: int, directionalAccuracy: float|null}> */
    public function report(?string $market = null): array
    {
        $query = AnalysisHistory::query()
            ->whereNotNull('outcome_correct')
            ->whereNotNull('forward_return')
            ->whereNotNull('sub_scores');

        if ($market) {
            $query->where('market', $market);
        }

        $rows = $query->get(['sub_scores', 'forward_return']);

        return array_map(function (string $key) use ($rows) {
            $matched = 0;
            $total = 0;
            foreach ($rows as $row) {
                $score = $row->sub_scores[$key]['score'] ?? null;
                if ($score === null || (float) $score === 0.0) {
                    continue;
                }
                $total++;
                if (($score > 0) === ($row->forward_return > 0)) {
                    $matched++;
                }
            }

            return [
                'subScore' => $key,
                'sampleSize' => $total,
                'directionalAccuracy' => $total > 0 ? round($matched / $total * 100, 1) : null,
            ];
        }, self::SUB_SCORE_KEYS);
    }

    // Turns the accuracy report above into plain-language suggestions a human can act on — never
    // an automatic weight change (see class docblock for why). Only fires once a sub-score has
    // enough samples, and only for sub-scores that actually appear in ScoringEngine::WEIGHTS, so
    // this stays correct if a sub-score is ever added or dropped there.
    /** @return array<int, array{subScore: string, sampleSize: int, directionalAccuracy: float, suggestion: string}> */
    public function weightSuggestions(?string $market = null): array
    {
        $weights = ScoringEngine::weights();
        $usedKeys = array_unique(array_merge(array_keys($weights['longterm']), array_keys($weights['trading'])));

        $suggestions = [];
        foreach ($this->report($market) as $row) {
            if (! in_array($row['subScore'], $usedKeys, true)) {
                continue;
            }
            if ($row['sampleSize'] < self::MIN_SAMPLE_FOR_SUGGESTION || $row['directionalAccuracy'] === null) {
                continue;
            }

            if ($row['directionalAccuracy'] < self::LOW_ACCURACY_THRESHOLD) {
                $suggestions[] = [...$row, 'suggestion' => "Akurasi arah cuma {$row['directionalAccuracy']}% dari {$row['sampleSize']} sampel — ".
                    'lebih sering meleset daripada benar, pertimbangkan turunkan bobotnya di ScoringEngine::WEIGHTS.'];
            } elseif ($row['directionalAccuracy'] > self::HIGH_ACCURACY_THRESHOLD) {
                $suggestions[] = [...$row, 'suggestion' => "Akurasi arah {$row['directionalAccuracy']}% dari {$row['sampleSize']} sampel — ".
                    'cukup kuat secara historis, mungkin layak dinaikkan bobotnya.'];
            }
        }

        return $suggestions;
    }
}
