<?php

namespace App\Services;

use App\Support\TechnicalIndicators;

// Rule-based scoring engine. Every sub-score is in the range [-1, 1] and every step is
// explainable — no black box. This is a decision-support heuristic, not a real predictor:
// financial markets are not reliably predictable, and this tool should never be read as one.
class ScoringEngine
{
    // Initial estimates, not derived from data. Once /api/accuracy's subScoreAccuracy has enough
    // samples (see SubScoreAccuracyService), use it to sanity-check whether these still make sense
    // — e.g. a sub-score with consistently poor directional accuracy is a candidate to de-weight.
    private const WEIGHTS = [
        // 'momentum' here is the ~20-day window (matches the trading horizon); the longer trend is
        // a distinct sub-score ('momentumLongTerm') so a short-term wiggle can't drive a "buy for
        // years" call, and a long-term uptrend can't drive a "buy for the next few weeks" call.
        'longterm' => ['fundamentals' => 0.45, 'news' => 0.15, 'momentumLongTerm' => 0.15, 'ownership' => 0.25],
        'trading' => ['fundamentals' => 0.1, 'news' => 0.3, 'momentum' => 0.4, 'ownership' => 0.2],
    ];

    public function buildAnalysis(
        ?array $fundamentals,
        ?array $newsArticles,
        ?array $priceSeries,
        ?array $ownershipTransactions,
        string $currency,
        ?array $benchmarkSeries = null,
        ?string $benchmarkLabel = null,
    ): array {
        $currentPrice = $priceSeries[0]['close'] ?? null;
        $fundamentalResult = $this->scoreFundamentals($fundamentals, $currentPrice, $currency);
        $momentumResult = $this->scoreMomentum($priceSeries, $currency, $benchmarkSeries, $benchmarkLabel);
        $longTermTrendResult = $this->scoreLongTermTrend($priceSeries);
        $ownershipResult = $this->scoreOwnership($ownershipTransactions, $currency);

        $newsArticles = $newsArticles ?? [];
        $newsResult = $this->scoreNews($newsArticles);

        $subScores = [
            'fundamentals' => $fundamentalResult,
            'news' => $newsResult,
            'momentum' => $momentumResult,
            'momentumLongTerm' => $longTermTrendResult,
            'ownership' => $ownershipResult,
        ];

        $longtermScore = $this->combine($subScores, 'longterm');
        $tradingScore = $this->combine($subScores, 'trading');

        $available = count(array_filter($subScores, fn ($s) => $s['score'] !== null));

        return [
            'subScores' => [
                'fundamentals' => ['score' => $fundamentalResult['score'], 'notes' => $fundamentalResult['notes']],
                'news' => [
                    'score' => $newsResult['score'],
                    'notes' => $newsResult['notes'],
                    'topArticles' => array_slice($newsArticles, 0, 5),
                ],
                'momentum' => ['score' => $momentumResult['score'], 'notes' => $momentumResult['notes']],
                'momentumLongTerm' => ['score' => $longTermTrendResult['score'], 'notes' => $longTermTrendResult['notes']],
                'ownership' => [
                    'score' => $ownershipResult['score'],
                    'notes' => $ownershipResult['notes'],
                    'transactions' => $ownershipResult['transactions'] ?? [],
                ],
            ],
            'dataCompleteness' => ['available' => $available, 'total' => count($subScores)],
            'lowLiquidity' => $momentumResult['lowLiquidity'] ?? false,
            'longterm' => ['score' => $longtermScore, 'label' => $this->labelFor($longtermScore)],
            'trading' => ['score' => $tradingScore, 'label' => $this->labelFor($tradingScore)],
            'disclaimer' => 'Ini analisis berbasis aturan sederhana (bukan prediksi yang terjamin akurat). '.
                'Gunakan sebagai salah satu bahan pertimbangan, bukan satu-satunya dasar keputusan investasi/trading.',
        ];
    }

    private function scoreFundamentals(?array $f, ?float $currentPrice, string $currency): array
    {
        if (! $f) {
            return ['score' => null, 'notes' => ['Data fundamental tidak tersedia.']];
        }

        $notes = [];
        $parts = [];
        $contextNotes = $this->fundamentalContextNotes($f);

        if ($this->isNum($f['revenueGrowthYoy'] ?? null)) {
            $v = $f['revenueGrowthYoy'];
            $s = $v > 0.1 ? 1 : ($v > 0 ? 0.5 : ($v > -0.05 ? -0.2 : -1));
            $parts[] = $s;
            $notes[] = "Pertumbuhan pendapatan YoY {$this->pct($v)} ({$this->describe($s)})";
        }
        if ($this->isNum($f['earningsGrowthYoy'] ?? null)) {
            $v = $f['earningsGrowthYoy'];
            $s = $v > 0.1 ? 1 : ($v > 0 ? 0.5 : ($v > -0.05 ? -0.2 : -1));
            $parts[] = $s;
            $notes[] = "Pertumbuhan laba YoY {$this->pct($v)} ({$this->describe($s)})";
        }
        if ($this->isNum($f['profitMargin'] ?? null)) {
            $v = $f['profitMargin'];
            $s = $v > 0.15 ? 1 : ($v > 0.05 ? 0.3 : ($v > 0 ? -0.2 : -1));
            $parts[] = $s;
            $notes[] = "Margin laba {$this->pct($v)} ({$this->describe($s)})";
        }
        if ($this->isNum($f['returnOnEquity'] ?? null)) {
            $v = $f['returnOnEquity'];
            $s = $v > 0.15 ? 1 : ($v > 0.05 ? 0.3 : ($v > 0 ? -0.2 : -1));
            $parts[] = $s;
            $notes[] = "ROE {$this->pct($v)} ({$this->describe($s)})";
        }
        if ($this->isNum($f['peRatio'] ?? null)) {
            $v = $f['peRatio'];
            $s = $v <= 0 ? -1 : ($v < 15 ? 1 : ($v < 25 ? 0.3 : ($v < 40 ? -0.3 : -1)));
            $parts[] = $s;
            $notes[] = 'P/E '.number_format($v, 1)." ({$this->describe($s)})";
        }
        if ($this->isNum($f['debtToEquity'] ?? null)) {
            $v = $f['debtToEquity'];
            $s = $v < 0.5 ? 0.5 : ($v < 1.5 ? 0 : -0.5);
            $parts[] = $s;
            $notes[] = 'Debt-to-equity '.number_format($v, 2)." ({$this->describe($s)})";
        }
        // PEG < 1 means the stock is cheap relative to its own earnings growth (not sector-relative
        // — no free data source gives us a reliable sector-average P/E to compare against instead).
        if ($this->isNum($f['pegRatio'] ?? null)) {
            $v = $f['pegRatio'];
            $s = $v <= 0 ? -0.3 : ($v < 1 ? 1 : ($v < 2 ? 0.2 : -0.6));
            $parts[] = $s;
            $notes[] = 'PEG ratio '.number_format($v, 2)." ({$this->describe($s)})";
        }
        // Consensus analyst target vs. today's price — how much upside/downside the sell side
        // collectively sees. Already fetched by both providers as part of the same request, no
        // extra API cost.
        if ($this->isNum($f['analystTargetPrice'] ?? null) && $currentPrice && $currentPrice > 0) {
            $target = $f['analystTargetPrice'];
            $upside = ($target - $currentPrice) / $currentPrice;
            $s = $upside > 0.2 ? 1 : ($upside > 0.05 ? 0.5 : ($upside > -0.05 ? 0 : ($upside > -0.2 ? -0.5 : -1)));
            $parts[] = $s;
            $arah = $upside >= 0 ? 'naik' : 'turun';
            $notes[] = 'Target harga analis '.$this->pricePerShare($target, $currency).
                " (potensi {$arah} {$this->pct(abs($upside))} dari harga sekarang) ({$this->describe($s)})";
        }

        if (count($parts) === 0) {
            return ['score' => null, 'notes' => count($contextNotes) > 0 ? $contextNotes : ['Tidak ada rasio fundamental yang bisa dibaca.']];
        }
        $score = $this->clamp(array_sum($parts) / count($parts));

        return ['score' => $score, 'notes' => [...$notes, ...$contextNotes]];
    }

    // Notes that add context but never move the score — no free data source exists to make them
    // rigorous (sector-average ratios), or they're a risk flag rather than a directional signal
    // (earnings proximity).
    private function fundamentalContextNotes(array $f): array
    {
        $notes = [];

        if (! empty($f['sector'])) {
            $notes[] = "Sektor: {$f['sector']}";
        }

        if (! empty($f['nextEarningsDate'])) {
            $daysUntil = (strtotime($f['nextEarningsDate']) - strtotime('today')) / 86400;
            if ($daysUntil >= 0 && $daysUntil <= 14) {
                $days = (int) round($daysUntil);
                $notes[] = "Laporan keuangan berikutnya diperkirakan sekitar {$f['nextEarningsDate']} (~{$days} hari lagi) — volatilitas harga bisa meningkat menjelang rilis.";
            }
        }

        return $notes;
    }

    private function pricePerShare(float $v, string $currency): string
    {
        $isIdr = $currency === 'IDR';

        return ($isIdr ? 'Rp' : '$').number_format($v, $isIdr ? 0 : 2);
    }

    // A stock with very thin daily trading value makes RSI/MACD/momentum noisy (a handful of
    // trades can swing the close) and is harder to actually enter/exit at the analyzed price —
    // this is a confidence flag on the signal, not a directional score, so it never joins $parts.
    private const LOW_LIQUIDITY_THRESHOLD = ['IDR' => 1_000_000_000, 'USD' => 1_000_000];

    private function scoreMomentum(?array $series, string $currency, ?array $benchmarkSeries = null, ?string $benchmarkLabel = null): array
    {
        if (! $series || count($series) < 20) {
            return ['score' => null, 'notes' => ['Data harga/volume tidak cukup (butuh minimal 20 hari).'], 'lowLiquidity' => false];
        }
        $recent = array_slice($series, 0, 20); // newest first
        $closeNow = $recent[0]['close'] ?? null;
        $close20dAgo = $recent[19]['close'] ?? null;
        if (! $closeNow || ! $close20dAgo) {
            return ['score' => null, 'notes' => ['Data harga tidak lengkap.'], 'lowLiquidity' => false];
        }

        $momentum = ($closeNow - $close20dAgo) / $close20dAgo;

        $vol = array_values(array_filter(array_map(fn ($d) => $d['volume'] ?? null, $recent), fn ($v) => $v !== null));
        $volumeRatio = null;
        if (count($vol) >= 20) {
            $last5 = array_sum(array_slice($vol, 0, 5)) / 5;
            $prior15 = array_sum(array_slice($vol, 5, 15)) / 15;
            if ($prior15 > 0) {
                $volumeRatio = $last5 / $prior15;
            }
        }

        if ($volumeRatio !== null && $volumeRatio > 1.2) {
            $score = $momentum > 0 ? 1 : -1;
            $volumeNote = 'volume naik signifikan';
        } elseif ($volumeRatio !== null && $volumeRatio < 0.8) {
            $score = $momentum > 0 ? 0.3 : -0.3;
            $volumeNote = 'volume menurun (sinyal lemah)';
        } else {
            $score = $momentum > 0 ? 0.5 : -0.5;
            $volumeNote = 'volume relatif stabil';
        }

        $arah = $momentum >= 0 ? 'naik' : 'turun';
        $parts = [$this->clamp($score)];
        $notes = ["Harga {$arah} {$this->pct(abs($momentum))} dalam ~20 hari perdagangan, {$volumeNote} ({$this->describe($score)})"];

        // Technical indicators computed from as much history as the price series provides.
        // Each is optional — quietly skipped when there isn't enough history for it yet.
        $closesChrono = array_values(array_filter(
            array_reverse(array_column($series, 'close')),
            fn ($c) => $c !== null
        ));

        $rsi = TechnicalIndicators::rsi($closesChrono, 14);
        if ($rsi !== null) {
            if ($rsi >= 70) {
                $s = -0.4;
                $desc = 'jenuh beli / overbought — waspada potensi koreksi';
            } elseif ($rsi <= 30) {
                $s = 0.4;
                $desc = 'jenuh jual / oversold — potensi rebound';
            } else {
                $s = ($rsi - 50) / 50 * 0.3;
                $desc = $rsi >= 50 ? 'momentum naik moderat' : 'momentum turun moderat';
            }
            $parts[] = $this->clamp($s);
            $notes[] = 'RSI(14) '.number_format($rsi, 1)." ({$desc})";
        }

        $sma20 = TechnicalIndicators::sma($closesChrono, 20);
        $sma50 = TechnicalIndicators::sma($closesChrono, 50);
        if ($sma20 !== null && $sma50 !== null) {
            $bullish = $sma20 > $sma50;
            $parts[] = $bullish ? 0.5 : -0.5;
            $notes[] = $bullish
                ? 'SMA20 di atas SMA50 (golden cross — tren jangka menengah naik)'
                : 'SMA20 di bawah SMA50 (death cross — tren jangka menengah turun)';
        }

        $macd = TechnicalIndicators::macd($closesChrono);
        if ($macd !== null) {
            $bullish = $macd['histogram'] > 0;
            $parts[] = $bullish ? 0.4 : -0.4;
            $notes[] = $bullish
                ? 'MACD di atas garis sinyal (momentum bullish)'
                : 'MACD di bawah garis sinyal (momentum bearish)';
        }

        // Relative strength vs. a market index: outperforming the benchmark is a stronger bullish
        // signal than raw absolute momentum (a 5% gain means little if the whole market is up 8%).
        if ($benchmarkSeries && count($benchmarkSeries) >= 20) {
            $benchRecent = array_slice($benchmarkSeries, 0, 20);
            $benchNow = $benchRecent[0]['close'] ?? null;
            $bench20dAgo = $benchRecent[19]['close'] ?? null;
            if ($benchNow && $bench20dAgo) {
                $benchReturn = ($benchNow - $bench20dAgo) / $bench20dAgo;
                $relative = $momentum - $benchReturn;
                $s = $this->clamp($relative * 4);
                $parts[] = $s;
                $label = $benchmarkLabel ?? 'indeks acuan';
                $verb = $relative >= 0 ? 'Mengungguli' : 'Kalah dari';
                $notes[] = "{$verb} {$label} sebesar {$this->pct(abs($relative))} dalam ~20 hari ({$this->describe($s)})";
            }
        }

        $finalScore = $this->clamp(array_sum($parts) / count($parts));

        $avgDailyValue = array_sum(array_map(
            fn ($d) => ($d['close'] ?? 0) * ($d['volume'] ?? 0),
            $recent
        )) / count($recent);
        $threshold = self::LOW_LIQUIDITY_THRESHOLD[$currency] ?? self::LOW_LIQUIDITY_THRESHOLD['USD'];
        $lowLiquidity = $avgDailyValue < $threshold;
        if ($lowLiquidity) {
            $notes[] = 'Likuiditas rendah (rata-rata transaksi harian '.$this->money($avgDailyValue, $currency).
                ') — sinyal teknikal (RSI/MACD/momentum) kurang bisa diandalkan pada saham dengan volume tipis.';
        }

        return ['score' => $finalScore, 'notes' => $notes, 'lowLiquidity' => $lowLiquidity];
    }

    // A separate, longer window from scoreMomentum() — used only for the "longterm" horizon, so a
    // short-term wiggle can't drive a multi-year call and vice versa. Uses the longest window the
    // price series can support (up to 100 days); below 60 days there isn't enough history for a
    // trend distinct from scoreMomentum's 20-day one to be meaningful.
    private function scoreLongTermTrend(?array $series): array
    {
        if (! $series) {
            return ['score' => null, 'notes' => ['Data harga tidak tersedia untuk tren jangka panjang.']];
        }

        $closesChrono = array_values(array_filter(
            array_reverse(array_column($series, 'close')),
            fn ($c) => $c !== null
        ));

        $window = min(count($closesChrono), 100);
        if ($window < 60) {
            $have = count($closesChrono);

            return ['score' => null, 'notes' => ["Data harga belum cukup untuk tren jangka panjang (butuh minimal 60 hari, baru ada {$have})."]];
        }

        $windowed = array_slice($closesChrono, -$window);
        $closeNow = end($windowed);
        $closeStart = $windowed[0];
        $longReturn = ($closeNow - $closeStart) / $closeStart;

        $sma = TechnicalIndicators::sma($closesChrono, $window);
        $aboveSma = $sma !== null && $closeNow > $sma;

        $returnScore = $this->clamp($longReturn * 2);
        $arah = $longReturn >= 0 ? 'naik' : 'turun';
        $parts = [$returnScore];
        $notes = ["Harga {$arah} {$this->pct(abs($longReturn))} dalam ~{$window} hari perdagangan ({$this->describe($returnScore)})"];

        if ($sma !== null) {
            $smaScore = $aboveSma ? 0.4 : -0.4;
            $parts[] = $smaScore;
            $notes[] = $aboveSma
                ? "Harga di atas rata-rata {$window} hari (tren jangka panjang naik)"
                : "Harga di bawah rata-rata {$window} hari (tren jangka panjang turun)";
        }

        $score = $this->clamp(array_sum($parts) / count($parts));

        return ['score' => $score, 'notes' => $notes];
    }

    // Outlets with an editorial desk and a reputation to protect are weighted higher than an
    // unknown/small blog — a crude but transparent proxy for source reliability.
    private const REPUTABLE_SOURCES = [
        'reuters', 'bloomberg', 'the wall street journal', 'associated press', 'cnbc',
        'kontan', 'bisnis.com', 'kompas', 'detik finance', 'investor daily', 'katadata', 'antara',
    ];

    private function scoreNews(array $articles): array
    {
        if (count($articles) === 0) {
            return ['score' => null, 'notes' => ['Tidak ada berita relevan ditemukan.']];
        }

        $weightedSum = 0;
        $weightTotal = 0;
        foreach ($articles as $a) {
            $w = $this->sourceWeight($a['source'] ?? null);
            $weightedSum += ($a['sentimentScore'] ?? 0) * $w;
            $weightTotal += $w;
        }
        $score = $this->clamp($weightedSum / $weightTotal);
        $count = count($articles);

        return ['score' => $score, 'notes' => ["Sentimen rata-rata (tertimbang keandalan sumber) dari {$count} berita: {$this->describe($score)}"]];
    }

    private function sourceWeight(?string $source): float
    {
        if (! $source) {
            return 1.0;
        }
        $normalized = mb_strtolower(trim($source));
        foreach (self::REPUTABLE_SOURCES as $reputable) {
            if (str_contains($normalized, $reputable)) {
                return 1.3;
            }
        }

        return 1.0;
    }

    // Insider/owner open-market buying vs selling. Cluster buying by multiple distinct insiders is
    // read as a stronger confidence signal than a single insider's trade (which can be for personal
    // reasons unrelated to the company's outlook, e.g. diversification, tax planning).
    private function scoreOwnership(?array $transactions, string $currency): array
    {
        if ($transactions === null) {
            return ['score' => null, 'notes' => ['Data transaksi insider/pemilik tidak tersedia untuk saham ini.']];
        }
        $relevant = array_values(array_filter($transactions, fn ($t) => $t['type'] === 'buy' || $t['type'] === 'sell'));
        if (count($relevant) === 0) {
            return ['score' => null, 'notes' => ['Tidak ada transaksi beli/jual insider yang ditemukan baru-baru ini.']];
        }

        $buys = array_values(array_filter($relevant, fn ($t) => $t['type'] === 'buy'));
        $sells = array_values(array_filter($relevant, fn ($t) => $t['type'] === 'sell'));

        // Buyer/seller counts stay undecayed — "did several different people act" is about
        // breadth, not recency. Only the dollar value driving the score itself decays with age.
        $buyValue = $this->sumWeightedValue($buys);
        $sellValue = $this->sumWeightedValue($sells);
        $buyers = count(array_unique(array_map(fn ($t) => $t['insiderName'], $buys)));
        $sellers = count(array_unique(array_map(fn ($t) => $t['insiderName'], $sells)));

        if ($buyValue + $sellValue > 0) {
            $score = ($buyValue - $sellValue) / ($buyValue + $sellValue);
        } else {
            // No price/value data available — fall back to counting transactions instead of currency value.
            $score = (count($buys) - count($sells)) / count($relevant);
        }
        if ($score > 0 && $buyers >= 3) {
            $score *= 1.15;
        }
        if ($score < 0 && $sellers >= 3) {
            $score *= 1.15;
        }
        $score = $this->clamp($score);

        $notes = [
            "{$buyers} insider/pemilik membeli senilai {$this->money($buyValue, $currency)}, ".
                "{$sellers} menjual senilai {$this->money($sellValue, $currency)} → {$this->describe($score)}",
        ];
        if ($buyers >= 3 && $score > 0) {
            $notes[] = 'Pembelian dilakukan oleh beberapa orang berbeda (bukan satu pihak saja) — sinyal lebih meyakinkan.';
        }
        if ($sellers >= 3 && $score < 0) {
            $notes[] = 'Penjualan dilakukan oleh beberapa orang berbeda — bisa jadi kekhawatiran bersama, meski juga bisa sekadar kebutuhan pribadi masing-masing.';
        }
        if ($this->hasStaleTransaction($relevant)) {
            $notes[] = 'Transaksi yang lebih lama diberi bobot lebih kecil dari yang baru-baru ini.';
        }

        return ['score' => $score, 'notes' => $notes, 'transactions' => array_slice($relevant, 0, 8)];
    }

    private function sumWeightedValue(array $list): float
    {
        return array_sum(array_map(
            fn ($t) => ($t['value'] ?? 0) * $this->insiderTimeWeight($t['date'] ?? null),
            $list
        ));
    }

    private function hasStaleTransaction(array $list): bool
    {
        foreach ($list as $t) {
            if ($this->insiderTimeWeight($t['date'] ?? null) < 1.0) {
                return true;
            }
        }

        return false;
    }

    // Recent insider activity is a stronger signal than something that happened months ago — a
    // purchase from half a year back says little about the outlook today. A missing/unparseable
    // date isn't penalized (1.0): several sources don't always supply one, and it shouldn't be
    // punished as if it were stale.
    private function insiderTimeWeight(?string $date): float
    {
        if (! $date) {
            return 1.0;
        }
        try {
            $days = (new \DateTimeImmutable)->diff(new \DateTimeImmutable($date))->days;
        } catch (\Throwable $e) {
            return 1.0;
        }

        return match (true) {
            $days <= 30 => 1.0,
            $days <= 90 => 0.7,
            $days <= 180 => 0.4,
            default => 0.15,
        };
    }

    private function money(float $v, string $currency): string
    {
        $isIdr = $currency === 'IDR';
        $symbol = $isIdr ? 'Rp' : '$';
        if (! $v) {
            return "{$symbol}0";
        }
        if ($v >= 1_000_000_000) {
            return $symbol.number_format($v / 1_000_000_000, 1).($isIdr ? ' miliar' : 'B');
        }
        if ($v >= 1_000_000) {
            return $symbol.number_format($v / 1_000_000, 1).($isIdr ? ' juta' : 'M');
        }

        return $symbol.number_format(round($v), 0, ',', '.');
    }

    private function combine(array $subScores, string $horizon): ?float
    {
        $weights = self::WEIGHTS[$horizon];
        $weightedSum = 0;
        $weightTotal = 0;
        foreach ($weights as $key => $w) {
            $s = $subScores[$key] ?? null;
            if ($s && $s['score'] !== null) {
                $weightedSum += $s['score'] * $w;
                $weightTotal += $w;
            }
        }
        if ($weightTotal === 0) {
            return null;
        }

        return $this->clamp($weightedSum / $weightTotal);
    }

    private function labelFor(?float $score): string
    {
        if ($score === null) {
            return 'Data tidak cukup';
        }
        if ($score >= 0.5) {
            return 'Strong Buy';
        }
        if ($score >= 0.15) {
            return 'Buy';
        }
        if ($score > -0.15) {
            return 'Hold / Netral';
        }
        if ($score > -0.5) {
            return 'Sell';
        }

        return 'Strong Sell';
    }

    private function isNum($v): bool
    {
        return is_int($v) || is_float($v);
    }

    private function clamp(float $v): float
    {
        return max(-1, min(1, $v));
    }

    private function pct(float $v): string
    {
        return number_format($v * 100, 1).'%';
    }

    private function describe(float $s): string
    {
        if ($s >= 0.5) {
            return 'sangat positif';
        }
        if ($s >= 0.15) {
            return 'positif';
        }
        if ($s > -0.15) {
            return 'netral';
        }
        if ($s > -0.5) {
            return 'negatif';
        }

        return 'sangat negatif';
    }
}
