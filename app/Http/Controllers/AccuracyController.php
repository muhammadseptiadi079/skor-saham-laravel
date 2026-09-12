<?php

namespace App\Http\Controllers;

use App\Models\AnalysisHistory;
use Illuminate\Http\Request;

// Transparency panel: measures whether the "trading" label's predicted direction actually held up
// ~20 trading days later, based on rows BacktestService has graded so far. Never computed live —
// always reads whatever `stocks:evaluate-backtest` has accumulated.
class AccuracyController extends Controller
{
    public function index(Request $request)
    {
        $query = AnalysisHistory::query()->whereNotNull('outcome_correct');

        if ($market = $request->query('market')) {
            $query->where('market', $market);
        }

        $graded = $query->get(['trading_label', 'outcome_correct', 'forward_return']);

        if ($graded->isEmpty()) {
            return response()->json([
                'sampleSize' => 0,
                'accuracy' => null,
                'avgForwardReturnPct' => null,
                'byLabel' => [],
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
        ]);
    }
}
