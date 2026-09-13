<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

// Global/US market data via Alpha Vantage free tier (25 requests/day per key).
class AlphaVantageService
{
    private const BASE = 'https://www.alphavantage.co/query';

    private function apiKey(): string
    {
        return config('services.alpha_vantage.key', 'demo');
    }

    // Fundamentals: P/E, margin, revenue growth, ROE, etc.
    public function getOverview(string $ticker): ?array
    {
        $data = Http::get(self::BASE, [
            'function' => 'OVERVIEW',
            'symbol' => $ticker,
            'apikey' => $this->apiKey(),
        ])->json();

        if (empty($data) || isset($data['Note']) || isset($data['Information'])) {
            return null; // rate-limited or unknown ticker
        }

        return [
            'name' => $data['Name'] ?? $ticker,
            'peRatio' => $this->toNum($data['PERatio'] ?? null),
            'pegRatio' => $this->toNum($data['PEGRatio'] ?? null),
            'profitMargin' => $this->toNum($data['ProfitMargin'] ?? null),
            'revenueGrowthYoy' => $this->toNum($data['QuarterlyRevenueGrowthYOY'] ?? null),
            'earningsGrowthYoy' => $this->toNum($data['QuarterlyEarningsGrowthYOY'] ?? null),
            'debtToEquity' => null, // not directly provided by OVERVIEW
            'returnOnEquity' => $this->toNum($data['ReturnOnEquityTTM'] ?? null),
            'marketCap' => $this->toNum($data['MarketCapitalization'] ?? null),
            'analystTargetPrice' => $this->toNum($data['AnalystTargetPrice'] ?? null),
            'dividendYield' => $this->toNum($data['DividendYield'] ?? null),
            'payoutRatio' => null, // not provided by OVERVIEW
            'analystRatings' => $this->analystRatings($data),
            'beta' => $this->toNum($data['Beta'] ?? null),
            'fiftyTwoWeekLow' => $this->toNum($data['52WeekLow'] ?? null),
            'fiftyTwoWeekHigh' => $this->toNum($data['52WeekHigh'] ?? null),
            'exDividendDate' => ($data['ExDividendDate'] ?? null) && $data['ExDividendDate'] !== 'None' ? $data['ExDividendDate'] : null,
            'insidersPercentHeld' => null, // not provided by OVERVIEW
            'institutionsPercentHeld' => null, // not provided by OVERVIEW
            'sector' => $data['Sector'] ?? null,
        ];
    }

    private function analystRatings(array $data): ?array
    {
        $keys = ['StrongBuy' => 'strongBuy', 'Buy' => 'buy', 'Hold' => 'hold', 'Sell' => 'sell', 'StrongSell' => 'strongSell'];
        $ratings = [];
        foreach ($keys as $avKey => $key) {
            $v = $this->toNum($data["AnalystRating{$avKey}"] ?? null);
            if ($v === null) {
                return null;
            }
            $ratings[$key] = (int) $v;
        }

        return $ratings;
    }

    // News + sentiment (Alpha Vantage already scores each article -1..1)
    public function getNewsSentiment(string $ticker): ?array
    {
        $data = Http::get(self::BASE, [
            'function' => 'NEWS_SENTIMENT',
            'tickers' => $ticker,
            'limit' => 30,
            'apikey' => $this->apiKey(),
        ])->json();

        if (empty($data['feed']) || ! is_array($data['feed'])) {
            return null;
        }

        return array_map(function ($item) use ($ticker) {
            $match = collect($item['ticker_sentiment'] ?? [])->firstWhere('ticker', $ticker);

            return [
                'title' => $item['title'] ?? '',
                'url' => $item['url'] ?? null,
                'source' => $item['source'] ?? null,
                'publishedAt' => $item['time_published'] ?? null,
                'sentimentScore' => $match
                    ? (float) $match['ticker_sentiment_score']
                    : (float) ($item['overall_sentiment_score'] ?? 0),
                'sentimentLabel' => $match['ticker_sentiment_label'] ?? ($item['overall_sentiment_label'] ?? null),
            ];
        }, $data['feed']);
    }

    // Daily close + volume, used for the momentum/volume sub-score.
    public function getDailyTimeSeries(string $ticker): ?array
    {
        $data = Http::get(self::BASE, [
            'function' => 'TIME_SERIES_DAILY',
            'symbol' => $ticker,
            'outputsize' => 'compact',
            'apikey' => $this->apiKey(),
        ])->json();

        $series = $data['Time Series (Daily)'] ?? null;
        if (! $series) {
            return null;
        }

        $rows = [];
        foreach ($series as $date => $v) {
            $rows[] = [
                'date' => $date,
                'close' => $this->toNum($v['4. close'] ?? null),
                'volume' => $this->toNum($v['5. volume'] ?? null),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($b['date'], $a['date'])); // newest first

        return $rows;
    }

    // Upcoming IPOs (mostly US-listed). Real, documented, free endpoint — unlike the other
    // functions here it returns CSV, not JSON. https://www.alphavantage.co/documentation/#ipo-calendar
    public function getIpoCalendar(): array
    {
        $csv = Http::get(self::BASE, [
            'function' => 'IPO_CALENDAR',
            'apikey' => $this->apiKey(),
        ])->body();

        $lines = array_values(array_filter(array_map('trim', explode("\n", $csv))));
        if (count($lines) < 2) {
            return [];
        }

        $header = str_getcsv(array_shift($lines));
        $rows = [];
        foreach ($lines as $line) {
            $fields = str_getcsv($line);
            if (count($fields) !== count($header)) {
                continue;
            }
            $row = array_combine($header, $fields);
            $rows[] = [
                'ticker' => $row['symbol'] ?? null,
                'companyName' => $row['name'] ?? ($row['symbol'] ?? 'Unknown'),
                'ipoDate' => $row['ipoDate'] ?? null,
                'priceRange' => isset($row['priceRangeLow'], $row['priceRangeHigh'])
                    ? "{$row['priceRangeLow']}-{$row['priceRangeHigh']} ".($row['currency'] ?? 'USD')
                    : null,
            ];
        }

        return $rows;
    }

    private function toNum($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_numeric($v) ? (float) $v : null;
    }
}
