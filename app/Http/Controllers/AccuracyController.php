<?php

namespace App\Http\Controllers;

use App\Models\AnalysisHistory;
use App\Services\SubScoreAccuracyService;
use Illuminate\Http\Request;

// Transparency panel: measures whether the "trading" label's predicted direction actually held up
// ~20 trading days later, based on rows BacktestService has graded so far. Never computed live —
// always reads whatever `stocks:evaluate-backtest` has accumulated.
class AccuracyController extends Controller
{
    public function __construct(private SubScoreAccuracyService $subScoreAccuracy) {}

    public function index(Request $request)
    {
        $market = $request->query('market');
        $subScoreAccuracy = $this->subScoreAccuracy->report($market);

        $query = AnalysisHistory::query()->whereNotNull('outcome_correct');
        if ($market) {
            $query->where('market', $market);
        }

        $graded = $query->get(['trading_label', 'outcome_correct', 'forward_return']);

        if ($graded->isEmpty()) {
            return response()->json([
                'sampleSize' => 0,
                'accuracy' => null,
                'avgForwardReturnPct' => null,
                'byLabel' => [],
                'subScoreAccuracy' => $subScoreAccuracy,
            ]);
        }

        $byLabel = $graded->groupBy('trading_label')->map(function ($rows, $label) {
            $correct = $rows->where('outcome_correct', true)->count();

            return [
                'label' => $label,
                'sampleSize' => $rows->count(),
                'correct' => $correct,
                'accuracy' => round($correct / $rows->count() * 100, 1),
                'avgForwardReturnPct' => round($rows->avg('forward_return') * 100, 2),
            ];
        })->values();

        $totalCorrect = $graded->where('outcome_correct', true)->count();

        return response()->json([
            'sampleSize' => $graded->count(),
            'accuracy' => round($totalCorrect / $graded->count() * 100, 1),
            'avgForwardReturnPct' => round($graded->avg('forward_return') * 100, 2),
            'byLabel' => $byLabel,
            'subScoreAccuracy' => $subScoreAccuracy,
        ]);
    }
}
