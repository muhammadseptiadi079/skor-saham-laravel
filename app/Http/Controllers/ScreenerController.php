<?php

namespace App\Http\Controllers;

use App\Models\AnalysisHistory;
use App\Models\WatchlistItem;
use App\Support\Sectors;
use App\Support\SectorStarterPacks;
use Illuminate\Http\Request;

// "Potential gainers" screener. Reads the latest cached score per ticker from analysis_history
// (populated by real user searches and by the scheduled stocks:refresh-scores command) — it never
// computes scores live on request, since that would blow through Alpha Vantage's free-tier rate
// limit on a single page load.
class ScreenerController extends Controller
{
    private const GAINER_THRESHOLD = 0.15; // matches ScoringEngine's "Buy" label cutoff

    // Same conservative sample-size gate used everywhere else a backtest-derived percentage is
    // shown next to a specific call (see ResultPanel's MIN_SAMPLE_FOR_HISTORICAL_NOTE on the
    // frontend) — below this, showing a number invites more confidence than the sample supports.
    private const MIN_SAMPLE_FOR_RETURN = 10;

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

        // Backtest only ever grades the "trading" label (see BacktestService), so an average
        // return by label only means anything for the trading horizon — never attached for
        // longterm, rather than accidentally showing trading-horizon history next to a
        // longterm call just because the label text happens to match.
        $avgReturnByLabel = $horizon === 'trading' ? $this->avgReturnByLabel($market) : collect();

        $watchlistSectors = WatchlistItem::where('market', $market)->pluck('sector', 'ticker');

        $items = $rows->map(function ($row) use ($labelColumn, $avgReturnByLabel, $watchlistSectors) {
            $label = $row->{$labelColumn};
            $accuracy = $label ? $avgReturnByLabel->get($label) : null;
            $sector = $watchlistSectors[$row->ticker]
                ?? SectorStarterPacks::sectorForTicker($row->ticker)
                ?? Sectors::DEFAULT;

            return array_merge($row->toArray(), [
                'sector' => $sector,
                'avgForwardReturnPct' => ($accuracy && $accuracy['sampleSize'] >= self::MIN_SAMPLE_FOR_RETURN)
                    ? $accuracy['avgForwardReturnPct']
                    : null,
            ]);
        });

        return response()->json([
            'market' => $market,
            'horizon' => $horizon,
            'generatedFrom' => 'cached_scores',
            'items' => $items,
        ]);
    }

    // Mirrors AccuracyController's byLabel breakdown (same graded rows, same average), just keyed
    // by label instead of assembled into the full accuracy report — kept local since this is the
    // only other place that needs it.
    private function avgReturnByLabel(string $market): \Illuminate\Support\Collection
    {
        $graded = AnalysisHistory::query()
            ->whereNotNull('outcome_correct')
            ->whereNotNull('forward_return')
            ->where('market', $market)
            ->get(['trading_label', 'forward_return']);

        return $graded->groupBy('trading_label')->map(fn ($rows) => [
            'sampleSize' => $rows->count(),
            'avgForwardReturnPct' => round($rows->avg('forward_return') * 100, 2),
        ]);
    }
}
