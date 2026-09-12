<?php

namespace App\Http\Controllers;

use App\Models\AnalysisHistory;
use Illuminate\Http\Request;

// "Potential gainers" screener. Reads the latest cached score per ticker from analysis_history
// (populated by real user searches and by the scheduled stocks:refresh-scores command) — it never
// computes scores live on request, since that would blow through Alpha Vantage's free-tier rate
// limit on a single page load.
class ScreenerController extends Controller
{
    private const GAINER_THRESHOLD = 0.15; // matches ScoringEngine's "Buy" label cutoff

    public function index(Request $request)
    {
        $market = $request->query('market', 'idx');
        if (! in_array($market, ['idx', 'global'], true)) {
            return response()->json([
                'error' => 'bad_request',
                'message' => 'market harus "idx" atau "global".',
            ], 400);
        }

        $horizon = $request->query('horizon', 'trading');
        $scoreColumn = $horizon === 'longterm' ? 'longterm_score' : 'trading_score';
        $labelColumn = $horizon === 'longterm' ? 'longterm_label' : 'trading_label';

        $latestIds = AnalysisHistory::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('ticker', 'market')
            ->pluck('id');

        $rows = AnalysisHistory::query()
            ->whereIn('id', $latestIds)
            ->where('market', $market)
            ->where($scoreColumn, '>=', self::GAINER_THRESHOLD)
            ->orderByDesc($scoreColumn)
            ->limit(20)
            ->get(['ticker', 'name', 'market', 'currency', $scoreColumn, $labelColumn, 'generated_at']);

        return response()->json([
            'market' => $market,
            'horizon' => $horizon,
            'generatedFrom' => 'cached_scores',
            'items' => $rows,
        ]);
    }
}
