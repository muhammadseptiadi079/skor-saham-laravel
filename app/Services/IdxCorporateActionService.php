<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Best-effort fetch of upcoming stock-split and rights-issue (HMETD) corporate actions from IDX's
// own public listed-company data endpoint. UNVERIFIED, same caveat as IdxIpoService/
// IdxForeignFlowService: this sandbox's network egress is blocked, so the endpoint/field names
// below follow the pattern IDX's own site uses for its corporate-action widgets, but could not be
// tested against a live response. Deliberately narrow scope: ex-dividend timing already has its
// own note (see ScoringEngine::fundamentalContextNotes, sourced directly from Yahoo/Alpha Vantage
// fundamentals) — this only covers actions that change the share count, which nothing else in the
// app surfaces. If it comes back empty in production, inspect the real response and fix
// parseRows()/classify() accordingly; this never being able to fetch data should degrade to null,
// not break the app.
class IdxCorporateActionService
{
    private const ENDPOINT = 'https://www.idx.co.id/primary/Corporate/GetCorporateAction';

    private const SPLIT_KEYWORDS = ['split', 'pemecahan saham'];

    private const RIGHTS_ISSUE_KEYWORDS = ['right issue', 'rights issue', 'hmetd'];

    /** @return array{type: string, date: string}|null */
    public function upcomingActionFor(string $ticker): ?array
    {
        $ticker = strtoupper(str_replace('.JK', '', $ticker));

        try {
            $data = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0',
                'Accept' => 'application/json',
            ])->get(self::ENDPOINT, [
                'kodeEmiten' => $ticker,
            ])->json();

            $rows = $data['data'] ?? $data['Results'] ?? null;
            if (! is_array($rows)) {
                return null;
            }

            return $this->nearestAction($rows, $ticker);
        } catch (\Throwable $e) {
            Log::warning('IDX corporate action fetch failed: '.$e->getMessage());

            return null;
        }
    }

    private function nearestAction(array $rows, string $ticker): ?array
    {
        $today = strtotime('today');
        $best = null;

        foreach ($rows as $row) {
            $rowTicker = strtoupper($row['Code'] ?? $row['KodeEmiten'] ?? $row['symbol'] ?? '');
            if ($rowTicker !== $ticker) {
                continue;
            }

            $type = $this->classify(strtolower($row['Subject'] ?? $row['Keterangan'] ?? $row['subject'] ?? ''));
            if (! $type) {
                continue;
            }

            $dateRaw = $row['ExDate'] ?? $row['RecordDate'] ?? $row['TanggalCumDate'] ?? $row['date'] ?? null;
            $timestamp = is_string($dateRaw) ? strtotime($dateRaw) : false;
            if (! $timestamp || $timestamp < $today) {
                continue;
            }

            if (! $best || $timestamp < $best['timestamp']) {
                $best = ['type' => $type, 'date' => date('Y-m-d', $timestamp), 'timestamp' => $timestamp];
            }
        }

        return $best ? ['type' => $best['type'], 'date' => $best['date']] : null;
    }

    private function classify(string $subject): ?string
    {
        foreach (self::SPLIT_KEYWORDS as $kw) {
            if (str_contains($subject, $kw)) {
                return 'split';
            }
        }
        foreach (self::RIGHTS_ISSUE_KEYWORDS as $kw) {
            if (str_contains($subject, $kw)) {
                return 'rights_issue';
            }
        }

        return null;
    }
}
