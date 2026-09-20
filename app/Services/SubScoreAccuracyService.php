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
    private const SUB_SCORE_KEYS = ['fundamentals', 'news', 'momentum', 'momentumLongTerm', 'ownership', 'foreignFlow'];

    // A sub-score needs at least this many graded samples before its accuracy is trusted enough to
    // even suggest a weight change — below this, a run of luck or bad luck is too likely to be
    // mistaken for a real pattern.
    private const MIN_SAMPLE_FOR_SUGGESTION = 20;

    private const LOW_ACCURACY_THRESHOLD = 45.0;

    private const HIGH_ACCURACY_THRESHOLD = 65.0;

    // Confidence calibration check (see confidenceCalibrationReport()): each end needs this
    // many graded samples before comparing them means anything, for the same reason
    // MIN_SAMPLE_FOR_SUGGESTION exists above — lower than that constant because splitting the
    // sample three ways (Tinggi/Sedang/Rendah) means each bucket fills up slower.
    private const MIN_SAMPLE_PER_CONFIDENCE_LEVEL = 15;

    // How many percentage points of accuracy "Tinggi" needs over "Rendah" before the confidence
    // score is considered to actually be distinguishing more-trustworthy calls from less-trustworthy
    // ones. Below this, the gap could plausibly just be noise.
    private const MEANINGFUL_CONFIDENCE_GAP = 10.0;

    private const CONFIDENCE_LEVELS = ['Tinggi', 'Sedang', 'Rendah'];

    private const STATUS_INSUFFICIENT_DATA = 'insufficient_data';

    private const STATUS_OK = 'ok';

    private const STATUS_NEEDS_REVIEW = 'needs_review';

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

    /** @return array<int, array{level: string, sampleSize: int, accuracy: float|null}> */
    public function confidenceAccuracy(?string $market = null): array
    {
        $query = AnalysisHistory::query()
            ->whereNotNull('outcome_correct')
            ->whereNotNull('trading_confidence');

        if ($market) {
            $query->where('market', $market);
        }

        $rows = $query->get(['trading_confidence', 'outcome_correct']);

        return array_map(function (string $level) use ($rows) {
            $matching = $rows->where('trading_confidence', $level);
            $total = $matching->count();

            return [
                'level' => $level,
                'sampleSize' => $total,
                'accuracy' => $total > 0 ? round($matching->where('outcome_correct', true)->count() / $total * 100, 1) : null,
            ];
        }, self::CONFIDENCE_LEVELS);
    }

    // Validates ScoringEngine::confidenceFor()'s own premise — that a "Tinggi" confidence call
    // should turn out correct meaningfully more often than a "Rendah" one — the same way
    // weightSuggestions() validates WEIGHTS, and with the same "report only, never auto-tune"
    // stance (see class docblock). Compares just the two extremes (skips "Sedang") since that's the
    // starkest test of whether the confidence score is distinguishing anything at all; if even
    // Tinggi-vs-Rendah isn't clearly separated, the ambang (threshold) in confidenceFor() — the
    // 0.8/0.5 agreement-ratio cutoffs — is a reasonable first place to look.
    //
    // Unlike weightSuggestions() this always returns something, never null: a report that goes
    // silent while data is still accumulating looks indistinguishable from a report that's broken.
    // `status` tells the caller which of three states this is — insufficient_data (not enough
    // samples yet to say anything), ok (calibration looks fine, nothing to act on), or
    // needs_review (the gap isn't meaningful) — and `message` is always a ready-to-display sentence
    // for whichever state it is.
    /** @return array{status: string, tinggiAccuracy: float|null, tinggiSampleSize: int, rendahAccuracy: float|null, rendahSampleSize: int, sampleNeededPerLevel: int, message: string} */
    public function confidenceCalibrationReport(?string $market = null): array
    {
        $byLevel = collect($this->confidenceAccuracy($market))->keyBy('level');
        $tinggi = $byLevel['Tinggi'];
        $rendah = $byLevel['Rendah'];
        $needed = self::MIN_SAMPLE_PER_CONFIDENCE_LEVEL;

        $base = [
            'tinggiAccuracy' => $tinggi['accuracy'],
            'tinggiSampleSize' => $tinggi['sampleSize'],
            'rendahAccuracy' => $rendah['accuracy'],
            'rendahSampleSize' => $rendah['sampleSize'],
            'sampleNeededPerLevel' => $needed,
        ];

        if ($tinggi['sampleSize'] < $needed || $rendah['sampleSize'] < $needed) {
            return [...$base, 'status' => self::STATUS_INSUFFICIENT_DATA, 'message' => "Baru {$tinggi['sampleSize']} dari {$needed} sampel graded di level \"Tinggi\" dan {$rendah['sampleSize']} dari {$needed} ".
                'di "Rendah" — belum cukup untuk menilai apakah skor keyakinan ini valid. Jalankan `php artisan '.
                'stocks:evaluate-backtest` secara berkala supaya datanya terkumpul.'];
        }

        $gap = round($tinggi['accuracy'] - $rendah['accuracy'], 1);
        if ($gap >= self::MEANINGFUL_CONFIDENCE_GAP) {
            return [...$base, 'status' => self::STATUS_OK, 'message' => "Kalibrasi terlihat baik: akurasi \"Tinggi\" ({$tinggi['accuracy']}% dari {$tinggi['sampleSize']} sampel) lebih ".
                "tinggi {$gap} poin dari \"Rendah\" ({$rendah['accuracy']}% dari {$rendah['sampleSize']} sampel) — skor keyakinan ".
                'ini kelihatan benar-benar membedakan mana kesimpulan yang lebih bisa dipercaya.'];
        }

        $verb = $gap <= 0 ? 'malah lebih rendah dari atau sama dengan' : 'tidak jauh berbeda dari';

        return [...$base, 'status' => self::STATUS_NEEDS_REVIEW, 'message' => "Akurasi \"Tinggi\" ({$tinggi['accuracy']}% dari {$tinggi['sampleSize']} sampel) {$verb} akurasi ".
            "\"Rendah\" ({$rendah['accuracy']}% dari {$rendah['sampleSize']} sampel) — skor keyakinan ini belum kelihatan ".
            'benar-benar membedakan mana kesimpulan yang lebih bisa dipercaya. Pertimbangkan tinjau ulang ambang di '.
            'ScoringEngine::confidenceFor() (CONFIDENCE_NEUTRAL_BAND dan batas rasio 0.8/0.5).'];
    }
}
