<?php

namespace App\Services;

use App\Models\AnalysisHistory;
use App\Models\WatchlistItem;
use App\Support\Sectors;

// Compares a stock's P/E and PEG ratio against other stocks in the same user-assigned sector,
// using the app's own accumulated analysis history — no free data source gives a reliable
// sector-average ratio for IDX tickers. Necessarily a small, self-selected sample (only
// sectors/tickers the user has already put in their own watchlist), so this is a rough
// comparison against "the sector as represented in your watchlist," not an authoritative
// sector benchmark. Purely informational — ScoringEngine never scores it.
class SectorValuationService
{
    private const MIN_PEERS = 2;

    public function averagesFor(string $ticker, string $market): ?array
    {
        $ticker = strtoupper($ticker);

        $sector = WatchlistItem::where('ticker', $ticker)->where('market', $market)->value('sector');
        if (! $sector || $sector === Sectors::DEFAULT) {
            return null;
        }

        $peerTickers = WatchlistItem::where('sector', $sector)
            ->where('market', $market)
            ->where('ticker', '!=', $ticker)
            ->pluck('ticker');

        if ($peerTickers->count() < self::MIN_PEERS) {
            return null;
        }

        $peValues = [];
        $pegValues = [];
        foreach ($peerTickers as $peerTicker) {
            $latest = AnalysisHistory::where('ticker', $peerTicker)
                ->where('market', $market)
                ->orderByDesc('generated_at')
                ->first(['pe_ratio', 'peg_ratio']);

            if ($latest?->pe_ratio) {
                $peValues[] = $latest->pe_ratio;
            }
            if ($latest?->peg_ratio) {
                $pegValues[] = $latest->peg_ratio;
            }
        }

        if (count($peValues) < self::MIN_PEERS && count($pegValues) < self::MIN_PEERS) {
            return null;
        }

        return [
            'sector' => $sector,
            'avgPe' => count($peValues) >= self::MIN_PEERS ? array_sum($peValues) / count($peValues) : null,
            'peSampleSize' => count($peValues),
            'avgPeg' => count($pegValues) >= self::MIN_PEERS ? array_sum($pegValues) / count($pegValues) : null,
            'pegSampleSize' => count($pegValues),
        ];
    }
}
