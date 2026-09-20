<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Best-effort fetch of the latest trading day's foreign buy/sell value for an IDX ticker, from
// IDX's own public trading-summary endpoint. UNVERIFIED, same caveat as IdxIpoService: this
// sandbox's network egress is blocked to idx.co.id (and every other external host), so this could
// not be tested against a live response while writing it. IDX publishes no official free API for
// this — the endpoint/field names below follow the pattern IDX's own site uses for its listed-
// company widgets, but the exact path or field names may have changed. Net foreign flow (asing
// net beli/jual) is one of the most closely watched signals by Indonesian retail traders and isn't
// derivable from Yahoo/Alpha Vantage's data at all, so it's worth this same best-effort treatment
// — if it comes back empty in production, inspect the real response and fix parseRow() accordingly;
// this never being able to fetch data should degrade to null, not break the app.
class IdxForeignFlowService
{
    private const ENDPOINT = 'https://www.idx.co.id/primary/TradingSummary/GetStockSummary';

    /** @return array{buyValue: float, sellValue: float, asOfDate: ?string}|null */
    public function latestFlowFor(string $ticker): ?array
    {
        $ticker = strtoupper(str_replace('.JK', '', $ticker));

        try {
            $data = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0',
                'Accept' => 'application/json',
            ])->get(self::ENDPOINT, [
                'kodeEmiten' => $ticker,
                'length' => 1,
            ])->json();

            $rows = $data['data'] ?? $data['Results'] ?? null;
            if (! is_array($rows) || count($rows) === 0) {
                return null;
            }

            return $this->parseRow($rows[0]);
        } catch (\Throwable $e) {
            Log::warning('IDX foreign flow fetch failed: '.$e->getMessage());

            return null;
        }
    }

    private function parseRow(array $row): ?array
    {
        $buyValue = $this->toNum($row['ForeignBuyValue'] ?? $row['ForeignBuy'] ?? $row['NilaiBeliAsing'] ?? null);
        $sellValue = $this->toNum($row['ForeignSellValue'] ?? $row['ForeignSell'] ?? $row['NilaiJualAsing'] ?? null);
        if ($buyValue === null || $sellValue === null) {
            return null;
        }

        $date = $row['Date'] ?? $row['TradeDate'] ?? $row['Tanggal'] ?? null;

        return [
            'buyValue' => $buyValue,
            'sellValue' => $sellValue,
            'asOfDate' => is_string($date) ? $date : null,
        ];
    }

    private function toNum($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_numeric($v) ? (float) $v : null;
    }
}
