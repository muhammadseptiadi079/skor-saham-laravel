<?php

namespace App\Http\Controllers;

use App\Models\AnalysisHistory;
use App\Services\SubScoreAccuracyService;
use App\Support\Sectors;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// Transparency panel: measures whether the "trading" label's predicted direction actually held up
// ~20 trading days later, based on rows BacktestService has graded so far. Never computed live —
// always reads whatever `stocks:evaluate-backtest` has accumulated.
class AccuracyController extends Controller
{
    private const REGIME_LABELS = ['bull' => 'Pasar Naik', 'bear' => 'Pasar Turun', 'sideways' => 'Pasar Sideways'];

    public function __construct(private SubScoreAccuracyService $subScoreAccuracy) {}

    public function index(Request $request)
    {
        $market = $request->query('market');
        $subScoreAccuracy = $this->subScoreAccuracy->report($market);
        $weightSuggestions = $this->subScoreAccuracy->weightSuggestions($market);

        $query = AnalysisHistory::query()
            ->whereNotNull('outcome_correct')
            ->leftJoin('watchlist_items', function ($join) {
                $join->on('analysis_history.ticker', '=', 'watchlist_items.ticker')
                    ->on('analysis_history.market', '=', 'watchlist_items.market');
            });
        if ($market) {
            $query->where('analysis_history.market', $market);
        }

        $graded = $query->get(['trading_label', 'outcome_correct', 'forward_return', 'market_regime', 'sector']);

        if ($graded->isEmpty()) {
            return response()->json([
                'sampleSize' => 0,
                'accuracy' => null,
                'avgForwardReturnPct' => null,
                'byLabel' => [],
                'byMarketRegime' => [],
                'byWatchlistSector' => [],
                'subScoreAccuracy' => $subScoreAccuracy,
                'weightSuggestions' => $weightSuggestions,
            ]);
        }

        $byLabel = $graded->groupBy('trading_label')->map(fn ($rows, $label) => $this->summarize($rows, $label))->values();

        $byMarketRegime = $graded->whereNotNull('market_regime')
            ->groupBy('market_regime')
            ->map(fn ($rows, $regime) => $this->summarize($rows, self::REGIME_LABELS[$regime] ?? $regime))
            ->values();

        // Excludes rows whose ticker isn't in the watchlist (null sector) or has no sector assigned
        // yet ("Lainnya") — neither is a meaningful group to report accuracy for.
        $byWatchlistSector = $graded->whereNotNull('sector')
            ->where('sector', '!=', Sectors::DEFAULT)
            ->groupBy('sector')
            ->map(fn ($rows, $sector) => $this->summarize($rows, $sector))
            ->values();

        $totalCorrect = $graded->where('outcome_correct', true)->count();

        return response()->json([
            'sampleSize' => $graded->count(),
            'accuracy' => round($totalCorrect / $graded->count() * 100, 1),
            'avgForwardReturnPct' => round($graded->avg('forward_return') * 100, 2),
            'byLabel' => $byLabel,
            'byMarketRegime' => $byMarketRegime,
            'byWatchlistSector' => $byWatchlistSector,
            'subScoreAccuracy' => $subScoreAccuracy,
            'weightSuggestions' => $weightSuggestions,
        ]);
    }

    private function summarize(Collection $rows, string $label): array
    {
        $correct = $rows->where('outcome_correct', true)->count();

        return [
            'label' => $label,
            'sampleSize' => $rows->count(),
            'correct' => $correct,
            'accuracy' => round($correct / $rows->count() * 100, 1),
            'avgForwardReturnPct' => round($rows->avg('forward_return') * 100, 2),
        ];
    }
}
