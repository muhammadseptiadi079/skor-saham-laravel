<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

// Unofficial, undocumented Yahoo Finance endpoints. No API key, but no uptime/rate guarantee.
// Used for IDX tickers (suffix .JK) since Alpha Vantage doesn't cover the Indonesian exchange.
class YahooFinanceService
{
    public function normalizeIdxTicker(string $ticker): string
    {
        $t = strtoupper(trim($ticker));

        return str_ends_with($t, '.JK') ? $t : "{$t}.JK";
    }

    public function getChart(string $ticker): ?array
    {
        return $this->getChartForSymbol($this->normalizeIdxTicker($ticker));
    }

    // Like getChart(), but skips the .JK ticker normalization — needed for index/benchmark
    // symbols (e.g. ^JKSE for IHSG) which aren't regular IDX-listed tickers.
    public function getChartForSymbol(string $symbol): ?array
    {
        $data = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get("https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}", [
                // 6mo gives enough daily bars for SMA50/MACD (needs 35-50+ closes), not just the
                // 20-day momentum window.
                'range' => '6mo',
                'interval' => '1d',
            ])->json();

        $result = $data['chart']['result'][0] ?? null;
        if (! $result) {
            return null;
        }

        $timestamps = $result['timestamp'] ?? [];
        $quote = $result['indicators']['quote'][0] ?? null;
        if (! $quote) {
            return null;
        }

        $series = [];
        foreach ($timestamps as $i => $ts) {
            $close = $quote['close'][$i] ?? null;
            if ($close === null) {
                continue;
            }
            $series[] = [
                'date' => gmdate('Y-m-d', $ts),
                'close' => $close,
                'volume' => $quote['volume'][$i] ?? null,
            ];
        }
        usort($series, fn ($a, $b) => strcmp($b['date'], $a['date'])); // newest first

        return [
            'currency' => $result['meta']['currency'] ?? 'IDR',
            'name' => $result['meta']['symbol'] ?? $symbol,
            'series' => $series,
        ];
    }

    // Best-effort fundamentals. Yahoo has been locking this endpoint behind a crumb/cookie for
    // some accounts — if it fails, callers should treat fundamentals as unavailable and fall back
    // to a neutral score rather than erroring out the whole analysis.
    public function getFundamentals(string $ticker): ?array
    {
        $symbol = $this->normalizeIdxTicker($ticker);
        try {
            $data = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get("https://query1.finance.yahoo.com/v10/finance/quoteSummary/{$symbol}", [
                    // calendarEvents and recommendationTrend ride along on this same request (no
                    // extra HTTP call) — see ScoringEngine's earnings-proximity note and analyst
                    // recommendation scoring.
                    'modules' => 'financialData,defaultKeyStatistics,summaryDetail,summaryProfile,calendarEvents,recommendationTrend',
                ])->json();

            $result = $data['quoteSummary']['result'][0] ?? null;
            if (! $result) {
                return null;
            }

            $fin = $result['financialData'] ?? [];
            $stats = $result['defaultKeyStatistics'] ?? [];
            $summary = $result['summaryDetail'] ?? [];
            $profile = $result['summaryProfile'] ?? [];
            $earningsDateRaw = $result['calendarEvents']['earnings']['earningsDate'][0]['raw'] ?? null;
            // trend[0] is the '0m' (current month) bucket — Yahoo orders it newest-first.
            $trendNow = $result['recommendationTrend']['trend'][0] ?? null;

            return [
                'peRatio' => $this->raw($summary['trailingPE'] ?? null),
                'pegRatio' => $this->raw($stats['pegRatio'] ?? null),
                'profitMargin' => $this->raw($fin['profitMargins'] ?? null),
                'revenueGrowthYoy' => $this->raw($fin['revenueGrowth'] ?? null),
                'earningsGrowthYoy' => $this->raw($fin['earningsGrowth'] ?? null),
                'debtToEquity' => $this->raw($fin['debtToEquity'] ?? null),
                'returnOnEquity' => $this->raw($fin['returnOnEquity'] ?? null),
                'marketCap' => $this->raw($stats['marketCap'] ?? ($summary['marketCap'] ?? null)),
                'analystTargetPrice' => $this->raw($fin['targetMeanPrice'] ?? null),
                'nextEarningsDate' => $earningsDateRaw ? gmdate('Y-m-d', $earningsDateRaw) : null,
                'dividendYield' => $this->raw($summary['dividendYield'] ?? null),
                'payoutRatio' => $this->raw($summary['payoutRatio'] ?? null),
                'analystRatings' => $trendNow ? [
                    'strongBuy' => (int) ($trendNow['strongBuy'] ?? 0),
                    'buy' => (int) ($trendNow['buy'] ?? 0),
                    'hold' => (int) ($trendNow['hold'] ?? 0),
                    'sell' => (int) ($trendNow['sell'] ?? 0),
                    'strongSell' => (int) ($trendNow['strongSell'] ?? 0),
                ] : null,
                // Display-only context (see ScoringEngine::scoreFundamentals) — not scored, since
                // there's no free source for sector-average ratios to compare it against.
                'sector' => $profile['sector'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    // Best-effort insider/owner transactions. Populated mostly for US-listed companies via the
    // same SEC Form 4 feed — for many IDX tickers it comes back empty. When empty, callers should
    // show "not available" rather than pretending there's no insider activity.
    public function getInsiderTransactions(string $ticker): ?array
    {
        $symbol = $this->normalizeIdxTicker($ticker);
        try {
            $data = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get("https://query1.finance.yahoo.com/v10/finance/quoteSummary/{$symbol}", [
                    'modules' => 'insiderTransactions',
                ])->json();

            $list = $data['quoteSummary']['result'][0]['insiderTransactions']['transactions'] ?? null;
            if (! is_array($list) || count($list) === 0) {
                return [];
            }

            return array_map(function ($t) {
                $text = strtolower($t['transactionText'] ?? '');
                $type = (str_contains($text, 'purchase') || str_contains($text, 'buy'))
                    ? 'buy'
                    : ((str_contains($text, 'sale') || str_contains($text, 'sell')) ? 'sell' : 'other');

                $shares = $this->raw($t['shares'] ?? null);
                $value = $this->raw($t['value'] ?? null);

                return [
                    'date' => isset($t['startDate']) ? gmdate('Y-m-d', $this->raw($t['startDate'])) : null,
                    'insiderName' => $t['filerName'] ?? 'Tidak diketahui',
                    'role' => $t['filerRelation'] ?? 'Insider',
                    'type' => $type,
                    'shares' => $shares,
                    'pricePerShare' => ($shares && $value) ? $value / $shares : null,
                    'value' => $value,
                ];
            }, $list);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function raw($field)
    {
        if ($field === null) {
            return null;
        }
        if (is_array($field) && array_key_exists('raw', $field)) {
            return $field['raw'];
        }

        return is_numeric($field) ? (float) $field : null;
    }
}
