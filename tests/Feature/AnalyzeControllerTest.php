<?php

namespace Tests\Feature;

use App\Models\AnalysisHistory;
use App\Models\ManualNewsItem;
use App\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// All external providers (Yahoo, Google News RSS, Alpha Vantage, SEC EDGAR) are mocked with
// Http::fake() — this sandbox's network egress is blocked to every external host, so these
// integrations can only be exercised this way here. Response shapes below mirror each service's
// real documented/observed schema as closely as possible; still worth a manual smoke test against
// the live APIs once this runs somewhere with network access.
class AnalyzeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_idx_returns_full_analysis_and_saves_history(): void
    {
        $timestamps = [];
        $closes = [];
        $volumes = [];
        $base = 1_000_000_000;
        for ($i = 0; $i < 25; $i++) {
            $timestamps[] = $base + $i * 86400;
            $closes[] = 3000 + $i * 10; // steady uptrend
            $volumes[] = 500_000;
        }

        Http::fake([
            'https://query1.finance.yahoo.com/v8/finance/chart/*' => Http::response([
                'chart' => ['result' => [[
                    'meta' => ['currency' => 'IDR', 'symbol' => 'BBCA.JK'],
                    'timestamp' => $timestamps,
                    'indicators' => ['quote' => [['close' => $closes, 'volume' => $volumes]]],
                ]]],
            ]),
            'https://query1.finance.yahoo.com/v10/finance/quoteSummary/*' => function ($request) {
                if (str_contains($request->url(), 'insiderTransactions')) {
                    return Http::response([
                        'quoteSummary' => ['result' => [[
                            'insiderTransactions' => ['transactions' => [[
                                'filerName' => 'Budi Santoso',
                                'filerRelation' => 'Direktur',
                                'transactionText' => 'Purchase',
                                'shares' => ['raw' => 10000],
                                'value' => ['raw' => 50_000_000],
                                'startDate' => ['raw' => 1_700_000_000],
                            ]]],
                        ]]],
                    ]);
                }

                return Http::response([
                    'quoteSummary' => ['result' => [[
                        'financialData' => [
                            'profitMargins' => ['raw' => 0.2],
                            'revenueGrowth' => ['raw' => 0.15],
                            'earningsGrowth' => ['raw' => 0.12],
                            'debtToEquity' => ['raw' => 0.3],
                            'returnOnEquity' => ['raw' => 0.2],
                        ],
                        'defaultKeyStatistics' => ['pegRatio' => ['raw' => 1.2], 'marketCap' => ['raw' => 1_000_000_000]],
                        'summaryDetail' => ['trailingPE' => ['raw' => 12]],
                    ]]],
                ]);
            },
            'https://news.google.com/rss/search*' => Http::response(
                '<?xml version="1.0"?><rss><channel><item>'
                    .'<title>Saham BBCA melonjak setelah laba naik</title>'
                    .'<link>https://example.com/a</link>'
                    .'<source>Detik</source>'
                    .'<pubDate>Mon, 01 Jan 2024 00:00:00 GMT</pubDate>'
                    .'</item></channel></rss>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $response = $this->getJson('/api/analyze?ticker=BBCA&market=idx');

        $response->assertOk();
        $response->assertJsonStructure([
            'ticker', 'market', 'name', 'currency', 'generatedAt',
            'subScores' => ['fundamentals', 'news', 'momentum', 'ownership'],
            'longterm' => ['score', 'label'],
            'trading' => ['score', 'label'],
            'horizonAlignment' => ['aligned', 'note'],
            'disclaimer',
        ]);
        $response->assertJsonPath('ticker', 'BBCA');
        $response->assertJsonPath('market', 'idx');
        $response->assertJsonPath('currency', 'IDR');

        $this->assertDatabaseCount('analysis_history', 1);
        $this->assertDatabaseHas('analysis_history', [
            'ticker' => 'BBCA', 'market' => 'idx', 'pe_ratio' => 12.0, 'peg_ratio' => 1.2,
        ]);
        $this->assertNotNull(AnalysisHistory::first()->trading_confidence);

        // Internal-only fields used for the backtest/sector-valuation cache must never leak into
        // the public response.
        $response->assertJsonMissingPath('peRatioAtGeneration');
        $response->assertJsonMissingPath('pegRatioAtGeneration');

        // The ^JKSE benchmark fetch reuses the same faked Yahoo chart endpoint above, so the
        // relative-momentum note should be present end-to-end, not just at the unit level.
        $momentumNotes = implode(' ', $response->json('subScores.momentum.notes'));
        $this->assertStringContainsString('IHSG', $momentumNotes);
    }

    public function test_national_theme_note_appears_when_watchlist_sector_matches_and_news_is_active(): void
    {
        WatchlistItem::create(['ticker' => 'KAEF', 'market' => 'idx', 'sector' => 'Kesehatan']);

        $timestamps = [];
        $closes = [];
        $volumes = [];
        $base = 1_000_000_000;
        for ($i = 0; $i < 25; $i++) {
            $timestamps[] = $base + $i * 86400;
            $closes[] = 1500 + $i * 5;
            $volumes[] = 500_000;
        }

        Http::fake([
            'https://query1.finance.yahoo.com/v8/finance/chart/*' => Http::response([
                'chart' => ['result' => [[
                    'meta' => ['currency' => 'IDR', 'symbol' => 'KAEF.JK'],
                    'timestamp' => $timestamps,
                    'indicators' => ['quote' => [['close' => $closes, 'volume' => $volumes]]],
                ]]],
            ]),
            'https://query1.finance.yahoo.com/v10/finance/quoteSummary/*' => Http::response([
                'quoteSummary' => ['result' => [['financialData' => [], 'defaultKeyStatistics' => [], 'summaryDetail' => []]]],
            ]),
            'https://news.google.com/rss/search*' => function ($request) {
                // The theme query gets 5 hits (active); the company-specific query gets none.
                $count = str_contains(urldecode($request->url()), 'kebakaran hutan') ? 5 : 0;
                $items = str_repeat(
                    '<item><title>Berita</title><link>https://example.com/a</link>'.
                    '<source>Detik</source><pubDate>Mon, 01 Jan 2024 00:00:00 GMT</pubDate></item>',
                    $count
                );

                return Http::response(
                    "<?xml version=\"1.0\"?><rss><channel>{$items}</channel></rss>",
                    200,
                    ['Content-Type' => 'application/xml']
                );
            },
        ]);

        $response = $this->getJson('/api/analyze?ticker=KAEF&market=idx');

        $response->assertOk();
        $notes = implode(' ', $response->json('subScores.fundamentals.notes'));
        $this->assertStringContainsString('kebakaran hutan', $notes);
    }

    public function test_manual_news_is_merged_into_the_news_sub_score(): void
    {
        ManualNewsItem::create([
            'ticker' => 'KAEF', 'market' => 'idx', 'text' => 'Laba KAEF melonjak tahun ini',
            'source' => 'typed', 'sentiment_score' => 1.0,
        ]);

        Http::fake([
            'https://query1.finance.yahoo.com/v8/finance/chart/*' => Http::response([
                'chart' => ['result' => [[
                    'meta' => ['currency' => 'IDR', 'symbol' => 'KAEF.JK'],
                    'timestamp' => [],
                    'indicators' => ['quote' => [['close' => [], 'volume' => []]]],
                ]]],
            ]),
            'https://query1.finance.yahoo.com/v10/finance/quoteSummary/*' => Http::response([
                'quoteSummary' => ['result' => [['financialData' => [], 'defaultKeyStatistics' => [], 'summaryDetail' => []]]],
            ]),
            // No automatic news at all -> the manual item should be the only article scored.
            'https://news.google.com/rss/search*' => Http::response(
                '<?xml version="1.0"?><rss><channel></channel></rss>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $response = $this->getJson('/api/analyze?ticker=KAEF&market=idx');

        $response->assertOk();
        $this->assertEquals(1.0, $response->json('subScores.news.score'));
        $topArticles = $response->json('subScores.news.topArticles');
        $this->assertSame('Laba KAEF melonjak tahun ini', $topArticles[0]['title']);
        $this->assertSame('Input manual', $topArticles[0]['source']);
        $this->assertNull($topArticles[0]['url']);
    }

    public function test_analyze_global_returns_full_analysis(): void
    {
        $series = [];
        for ($i = 0; $i < 25; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $series[$date] = ['4. close' => (string) (190 - $i * 0.5), '5. volume' => '1000000'];
        }

        Http::fake([
            'https://www.alphavantage.co/query*' => function ($request) use ($series) {
                $url = $request->url();
                if (str_contains($url, 'function=OVERVIEW')) {
                    return Http::response([
                        'Name' => 'Apple Inc',
                        'PERatio' => '12',
                        'ProfitMargin' => '0.25',
                        'QuarterlyRevenueGrowthYOY' => '0.15',
                        'QuarterlyEarningsGrowthYOY' => '0.12',
                        'ReturnOnEquityTTM' => '0.3',
                        'MarketCapitalization' => '1000000000',
                        'Sector' => 'Technology',
                    ]);
                }
                if (str_contains($url, 'function=NEWS_SENTIMENT')) {
                    return Http::response(['feed' => [[
                        'title' => 'Apple soars on strong earnings',
                        'url' => 'https://example.com/aapl',
                        'source' => 'Reuters',
                        'time_published' => '20240101T000000',
                        'overall_sentiment_score' => 0.5,
                        'ticker_sentiment' => [['ticker' => 'AAPL', 'ticker_sentiment_score' => '0.6', 'ticker_sentiment_label' => 'Bullish']],
                    ]]]);
                }
                if (str_contains($url, 'function=TIME_SERIES_DAILY')) {
                    return Http::response(['Time Series (Daily)' => $series]);
                }

                return Http::response([], 404);
            },
            'https://www.sec.gov/files/company_tickers.json' => Http::response([
                '0' => ['cik_str' => 320193, 'ticker' => 'AAPL', 'title' => 'Apple Inc'],
            ]),
            'https://data.sec.gov/submissions/*' => Http::response([
                'filings' => ['recent' => [
                    'form' => ['4'],
                    'accessionNumber' => ['0000320193-24-000010'],
                    'primaryDocument' => ['doc1.xml'],
                ]],
            ]),
            'https://www.sec.gov/Archives/*' => Http::response(
                '<ownershipDocument>'
                    .'<reportingOwner><reportingOwnerId><rptOwnerName>Jane Insider</rptOwnerName></reportingOwnerId>'
                    .'<reportingOwnerRelationship><isOfficer>1</isOfficer><officerTitle>CFO</officerTitle></reportingOwnerRelationship></reportingOwner>'
                    .'<periodOfReport>2024-01-15</periodOfReport>'
                    .'<nonDerivativeTable><nonDerivativeTransaction>'
                    .'<transactionCoding><transactionCode>P</transactionCode></transactionCoding>'
                    .'<transactionAmounts><transactionShares><value>1000</value></transactionShares>'
                    .'<transactionPricePerShare><value>150</value></transactionPricePerShare></transactionAmounts>'
                    .'</nonDerivativeTransaction></nonDerivativeTable></ownershipDocument>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $response = $this->getJson('/api/analyze?ticker=AAPL&market=global');

        $response->assertOk();
        $response->assertJsonPath('ticker', 'AAPL');
        $response->assertJsonPath('market', 'global');
        $response->assertJsonPath('currency', 'USD');
    }

    public function test_analyze_requires_ticker_and_market(): void
    {
        $this->getJson('/api/analyze')->assertStatus(400);
        $this->getJson('/api/analyze?ticker=AAPL')->assertStatus(400);
        $this->getJson('/api/analyze?ticker=AAPL&market=moon')->assertStatus(400);
    }

    public function test_history_write_failure_does_not_break_the_response(): void
    {
        // No fakes registered at all -> every upstream call throws, ScoringEngine gets all-nulls,
        // but the endpoint should still respond 200 with "data not enough" labels rather than 502,
        // matching the existing safe()-wrapping behavior in StockAnalysisService.
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $response = $this->getJson('/api/analyze?ticker=ZZZZ&market=global');

        $response->assertOk();
        $response->assertJsonPath('longterm.label', 'Data tidak cukup');
        $this->assertDatabaseCount('analysis_history', 1);
    }
}
