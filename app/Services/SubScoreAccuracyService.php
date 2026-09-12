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
}
