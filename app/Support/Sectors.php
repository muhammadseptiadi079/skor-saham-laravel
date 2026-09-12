<?php

namespace App\Support;

// A simple, user-assigned sector taxonomy for grouping the watchlist. Deliberately not derived
// from Yahoo/Alpha Vantage's sector field — that's free-text and inconsistent for IDX tickers
// (often missing entirely), so the user picks a sector themselves when adding a stock and can
// reclassify it later rather than relying on an unreliable external source.
class Sectors
{
    public const DEFAULT = 'Lainnya';

    public const ALL = [
        'Pertambangan',
        'Keuangan & Perbankan',
        'Kesehatan',
        'Konstruksi & Infrastruktur',
        'Konsumer & Ritel',
        'Energi',
        'Teknologi',
        'Properti & Real Estate',
        'Industri & Manufaktur',
        self::DEFAULT,
    ];
}
