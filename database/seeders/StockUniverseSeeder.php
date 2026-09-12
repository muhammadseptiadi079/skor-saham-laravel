<?php

namespace Database\Seeders;

use App\Models\StockUniverseItem;
use Illuminate\Database\Seeder;

// Seed list of well-known large-cap tickers for the gainers screener to scan alongside the
// user's own watchlist. LQ45 membership is reviewed by IDX periodically (roughly every 6
// months) — this list won't always be perfectly current; treat it as a reasonable default and
// adjust via the stock_universe_items table as needed.
class StockUniverseSeeder extends Seeder
{
    private const LQ45_SAMPLE = [
        'BBCA', 'BBRI', 'BMRI', 'BBNI', 'TLKM', 'ASII', 'UNVR', 'ICBP', 'INDF', 'KLBF',
        'ADRO', 'PGAS', 'PTBA', 'SMGR', 'INTP', 'ANTM', 'INCO', 'UNTR', 'GGRM', 'HMSP',
        'CPIN', 'EXCL', 'ISAT', 'AKRA', 'MDKA',
    ];

    private const GLOBAL_BLUECHIP = [
        'AAPL', 'MSFT', 'GOOGL', 'AMZN', 'NVDA', 'META', 'TSLA', 'JPM', 'JNJ', 'V',
        'WMT', 'PG', 'XOM', 'UNH', 'HD', 'MA', 'KO', 'PEP', 'DIS', 'NFLX',
    ];

    public function run(): void
    {
        foreach (self::LQ45_SAMPLE as $ticker) {
            StockUniverseItem::firstOrCreate(
                ['ticker' => $ticker, 'market' => 'idx'],
                ['category' => 'lq45'],
            );
        }

        foreach (self::GLOBAL_BLUECHIP as $ticker) {
            StockUniverseItem::firstOrCreate(
                ['ticker' => $ticker, 'market' => 'global'],
                ['category' => 'bluechip'],
            );
        }
    }
}
