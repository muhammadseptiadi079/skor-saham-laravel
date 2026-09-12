<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Best-effort fetch of recent/upcoming IDX IPOs from IDX's own public listed-company data
// endpoint. UNVERIFIED: this sandbox's network egress is blocked to idx.co.id (and every other
// external host), so this could not be tested against a live response while writing it. IDX
// publishes no official free API for this — the endpoint/shape below follows the pattern IDX's
// own site uses for its listed-company widgets, but the exact path or field names may have
// changed. If it comes back empty in production, inspect the real response (e.g. via
// `Http::get(...)->body()` in tinker or the site's network tab) and fix parseRows() accordingly —
// this never being able to fetch data should degrade to an empty list, not break the app.
class IdxIpoService
{
    private const ENDPOINT = 'https://www.idx.co.id/primary/ListedCompany/GetIPO';

    public function getRecentIpos(int $limit = 20): array
    {
        try {
            $data = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0',
                'Accept' => 'application/json',
            ])->get(self::ENDPOINT, [
                'length' => $limit,
                'start' => 0,
            ])->json();

            $rows = $data['data'] ?? $data['Results'] ?? null;
            if (! is_array($rows)) {
                return [];
            }

            return $this->parseRows($rows);
        } catch (\Throwable $e) {
            Log::warning('IDX IPO fetch failed: '.$e->getMessage());

            return [];
        }
    }

    private function parseRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $ticker = $row['Code'] ?? $row['KodeEmiten'] ?? $row['symbol'] ?? null;
            $name = $row['Name'] ?? $row['NamaEmiten'] ?? $row['name'] ?? null;
            $date = $row['ListingDate'] ?? $row['TanggalPencatatan'] ?? $row['listing_date'] ?? null;
            if (! $ticker || ! $name) {
                continue;
            }
            $out[] = [
                'ticker' => strtoupper($ticker),
                'companyName' => $name,
                'ipoDate' => $date,
                'priceRange' => $row['OfferPrice'] ?? $row['HargaPenawaran'] ?? null,
            ];
        }

        return $out;
    }
}
