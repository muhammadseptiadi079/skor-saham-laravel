<?php

namespace App\Services;

use App\Models\AnalysisHistory;

// Flags when a stock is getting an unusually high volume of news coverage right now, compared to
// its own recent history — "normal" article count varies too much by company size (a bank vs. a
// small-cap) for any single absolute threshold to mean the same thing for every ticker, so this
// compares each stock only against itself. Says nothing about whether the coverage is good or bad
// news, only that there's more of it than usual — a "go read what's happening" flag, never scored.
class NewsVolumeService
{
    private const MIN_HISTORY = 3;

    private const SPIKE_MULTIPLIER = 2.0;

    // Avoids flagging noise like "1 article -> 2 articles" as a meaningful spike.
    private const MIN_CURRENT_COUNT = 5;

    public function spikeNoteFor(string $ticker, string $market, int $currentCount): ?string
    {
        if ($currentCount < self::MIN_CURRENT_COUNT) {
            return null;
        }

        $counts = AnalysisHistory::where('ticker', strtoupper($ticker))
            ->where('market', $market)
            ->whereNotNull('news_article_count')
            ->orderByDesc('generated_at')
            ->limit(10)
            ->pluck('news_article_count');

        if ($counts->count() < self::MIN_HISTORY) {
            return null;
        }

        $baseline = $counts->avg();
        if ($baseline <= 0 || $currentCount < $baseline * self::SPIKE_MULTIPLIER) {
            return null;
        }

        return "Jumlah berita soal saham ini ({$currentCount} artikel) jauh di atas rata-rata biasanya (".
            round($baseline, 1)." artikel dari {$counts->count()} analisis terakhir) — lagi jadi sorotan, ".
            'entah karena kabar baik atau buruk. Cek sendiri isi beritanya.';
    }
}
