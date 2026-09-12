<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// SEC EDGAR: official, free, no API key. Used for insider (Form 4) transactions on
// global/US tickers. SEC asks that requests carry an identifying User-Agent — see
// SEC_USER_AGENT in .env. https://www.sec.gov/os/accessing-edgar-data
class SecEdgarService
{
    private function headers(): array
    {
        return ['User-Agent' => config('services.sec_edgar.user_agent', 'SkorSahamPWA/1.0 (personal, non-commercial use)')];
    }

    private function loadTickerCikMap(): array
    {
        return Cache::remember('sec_edgar_ticker_cik_map', now()->addDay(), function () {
            $data = Http::withHeaders($this->headers())
                ->get('https://www.sec.gov/files/company_tickers.json')
                ->json();

            $map = [];
            foreach ($data as $entry) {
                $map[strtoupper($entry['ticker'])] = str_pad((string) $entry['cik_str'], 10, '0', STR_PAD_LEFT);
            }

            return $map;
        });
    }

    public function getCikForTicker(string $ticker): ?string
    {
        $map = $this->loadTickerCikMap();

        return $map[strtoupper($ticker)] ?? null;
    }

    private function getRecentForm4Filings(string $cik, int $limit = 10): array
    {
        $data = Http::withHeaders($this->headers())
            ->get("https://data.sec.gov/submissions/CIK{$cik}.json")
            ->json();

        $recent = $data['filings']['recent'] ?? null;
        if (! $recent) {
            return [];
        }

        $filings = [];
        foreach ($recent['form'] as $i => $form) {
            if ($form !== '4') {
                continue;
            }
            $filings[] = [
                'accessionNumber' => $recent['accessionNumber'][$i],
                'primaryDocument' => $recent['primaryDocument'][$i],
            ];
            if (count($filings) >= $limit) {
                break;
            }
        }

        return $filings;
    }

    private function docUrl(string $cik, array $filing): string
    {
        $accessionNoDashes = str_replace('-', '', $filing['accessionNumber']);
        $cikNum = (string) (int) $cik; // SEC archive paths use the un-padded CIK

        return "https://www.sec.gov/Archives/edgar/data/{$cikNum}/{$accessionNoDashes}/{$filing['primaryDocument']}";
    }

    // Parses one Form 4 XML document into normalized entries. Only open-market Buy ("P") and
    // Sell ("S") transactions are kept — option exercises, grants, gifts, and tax-withholding
    // (codes M, A, G, F, ...) don't reflect the "is the owner voluntarily buying/selling on the
    // open market" signal we're after.
    public function parseForm4(string $xml): array
    {
        $prevSetting = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($prevSetting);

        if ($doc === false) {
            return [];
        }

        $owners = $doc->reportingOwner;
        $ownerName = 'Tidak diketahui';
        $role = 'Insider';
        if (isset($owners[0])) {
            $owner = $owners[0];
            $ownerName = (string) ($owner->reportingOwnerId->rptOwnerName ?? 'Tidak diketahui');
            $rel = $owner->reportingOwnerRelationship ?? null;
            if ($rel) {
                if ((string) ($rel->isDirector ?? '') === '1') {
                    $role = 'Direktur/Komisaris';
                } elseif ((string) ($rel->isOfficer ?? '') === '1') {
                    $title = (string) ($rel->officerTitle ?? '');
                    $role = $title !== '' ? $title : 'Eksekutif';
                } elseif ((string) ($rel->isTenPercentOwner ?? '') === '1') {
                    $role = 'Pemegang saham >10%';
                }
            }
        }

        $date = (string) ($doc->periodOfReport ?? '');
        $transactions = $doc->nonDerivativeTable->nonDerivativeTransaction ?? [];

        $out = [];
        foreach ($transactions as $t) {
            $code = (string) ($t->transactionCoding->transactionCode ?? '');
            if ($code !== 'P' && $code !== 'S') {
                continue;
            }
            $sharesNode = $t->transactionAmounts->transactionShares->value ?? null;
            $priceNode = $t->transactionAmounts->transactionPricePerShare->value ?? null;
            if ($sharesNode === null || (string) $sharesNode === '') {
                continue;
            }
            $shares = (float) $sharesNode;
            $price = ($priceNode !== null && (string) $priceNode !== '') ? (float) $priceNode : null;

            $out[] = [
                'date' => $date,
                'insiderName' => $ownerName,
                'role' => $role,
                'type' => $code === 'P' ? 'buy' : 'sell',
                'shares' => $shares,
                'pricePerShare' => $price,
                'value' => $price !== null ? $shares * $price : null,
            ];
        }

        return $out;
    }

    public function getInsiderTransactions(string $ticker, int $limit = 10): ?array
    {
        $cik = $this->getCikForTicker($ticker);
        if (! $cik) {
            return null;
        }

        $filings = $this->getRecentForm4Filings($cik, $limit);
        if (count($filings) === 0) {
            return [];
        }

        $all = [];
        foreach ($filings as $filing) {
            try {
                $xml = Http::withHeaders($this->headers())->get($this->docUrl($cik, $filing))->body();
                $all = array_merge($all, $this->parseForm4($xml));
            } catch (\Throwable $e) {
                continue; // one bad filing shouldn't sink the whole request
            }
        }

        usort($all, fn ($a, $b) => strcmp($b['date'], $a['date'])); // newest first

        return $all;
    }
}
