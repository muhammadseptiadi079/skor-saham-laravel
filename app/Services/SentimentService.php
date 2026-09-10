<?php

namespace App\Services;

// Simple keyword-based sentiment scorer for Indonesian financial headlines.
// This is NOT a trained NLP model — it's a transparent, explainable heuristic:
// count positive vs negative finance-related words per headline, average the result.
// Good enough as one input signal; not a substitute for real sentiment analysis.
class SentimentService
{
    private const POSITIVE_WORDS = [
        'laba', 'untung', 'naik', 'melonjak', 'meroket', 'menguat', 'tumbuh', 'ekspansi',
        'akuisisi', 'dividen', 'rekor', 'positif', 'optimis', 'pulih', 'melesat',
        'surplus', 'kinerja apik', 'kinerja moncer', 'cuan', 'bullish', 'terbaik',
        'meningkat', 'melampaui', 'capaian', 'sukses', 'pertumbuhan', 'kenaikan',
    ];

    private const NEGATIVE_WORDS = [
        'rugi', 'turun', 'anjlok', 'merosot', 'melemah', 'terpuruk', 'krisis', 'negatif',
        'pesimis', 'gagal', 'default', 'utang', 'defisit', 'phk', 'bangkrut', 'skandal',
        'penurunan', 'koreksi', 'bearish', 'tekanan', 'terjun', 'gugat', 'sanksi',
        'penyelidikan', 'terseret', 'kerugian', 'delisting', 'suspensi', 'suspend',
    ];

    public function scoreHeadline(string $title): float
    {
        $text = mb_strtolower($title);
        $score = 0;
        foreach (self::POSITIVE_WORDS as $w) {
            if (str_contains($text, $w)) {
                $score += 1;
            }
        }
        foreach (self::NEGATIVE_WORDS as $w) {
            if (str_contains($text, $w)) {
                $score -= 1;
            }
        }
        return max(-1, min(1, $score));
    }

    public function scoreArticles(array $articles): array
    {
        if (count($articles) === 0) {
            return [];
        }
        return array_map(function ($a) {
            $a['sentimentScore'] = $this->scoreHeadline($a['title']);
            return $a;
        }, $articles);
    }
}
