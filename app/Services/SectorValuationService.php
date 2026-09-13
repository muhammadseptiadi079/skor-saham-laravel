<?php

namespace App\Services;

use App\Models\AnalysisHistory;
use App\Models\WatchlistItem;
use App\Support\Sectors;

// Compares a stock's P/E, PEG, profit margin, and ROE against other stocks in the same
// user-assigned sector, using the app's own accumulated analysis history — no free data source
// gives a reliable sector-average for IDX tickers. Necessarily a small, self-selected sample (only
// sectors/tickers the user has already put in their own watchlist), so this is a rough
// comparison against "the sector as represented in your watchlist," not an authoritative
// sector benchmark. Purely informational — ScoringEngine never scores it.
class SectorValuationService
{
    private const MIN_PEERS = 2;

    // The user-assigned sector for a ticker, or null if it's not in the watchlist (or has no
    // sector assigned yet). Also used by NationalThemeService to decide which national/macro news
    // themes are even worth checking for a given analysis.
    public function sectorFor(string $ticker, string $market): ?string
    {
        $sector = WatchlistItem::where('ticker', strtoupper($ticker))->where('market', $market)->value('sector');

        return ($sector && $sector !== Sectors::DEFAULT) ? $sector : null;
    }

    public function averagesFor(string $ticker, string $market): ?array
    {
        $ticker = strtoupper($ticker);

        $sector = $this->sectorFor($ticker, $market);
        if (! $sector) {
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
        $marginValues = [];
        $roeValues = [];
        foreach ($peerTickers as $peerTicker) {
            $latest = AnalysisHistory::where('ticker', $peerTicker)
                ->where('market', $market)
                ->orderByDesc('generated_at')
                ->first(['pe_ratio', 'peg_ratio', 'profit_margin', 'roe']);

            if ($latest?->pe_ratio) {
                $peValues[] = $latest->pe_ratio;
            }
            if ($latest?->peg_ratio) {
                $pegValues[] = $latest->peg_ratio;
            }
            if ($latest?->profit_margin !== null) {
                $marginValues[] = $latest->profit_margin;
            }
            if ($latest?->roe !== null) {
                $roeValues[] = $latest->roe;
            }
        }

        if (count($peValues) < self::MIN_PEERS && count($pegValues) < self::MIN_PEERS
            && count($marginValues) < self::MIN_PEERS && count($roeValues) < self::MIN_PEERS) {
            return null;
        }

        return [
            'sector' => $sector,
            'avgPe' => count($peValues) >= self::MIN_PEERS ? array_sum($peValues) / count($peValues) : null,
            'peSampleSize' => count($peValues),
            'avgPeg' => count($pegValues) >= self::MIN_PEERS ? array_sum($pegValues) / count($pegValues) : null,
            'pegSampleSize' => count($pegValues),
            'avgProfitMargin' => count($marginValues) >= self::MIN_PEERS ? array_sum($marginValues) / count($marginValues) : null,
            'profitMarginSampleSize' => count($marginValues),
            'avgRoe' => count($roeValues) >= self::MIN_PEERS ? array_sum($roeValues) / count($roeValues) : null,
            'roeSampleSize' => count($roeValues),
        ];
    }
}
