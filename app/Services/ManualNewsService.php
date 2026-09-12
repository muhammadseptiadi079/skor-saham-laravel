<?php

namespace App\Services;

use App\Models\ManualNewsItem;

// Stores user-submitted news (typed or OCR'd from a screenshot) and feeds recent ones back into
// that ticker's news sub-score on future analyses — for news the automatic feeds (Google News RSS,
// Alpha Vantage News Sentiment) missed. Scored through the exact same SentimentService dictionary
// as automatic news, so it's judged by the same transparent rules, not a separate standard.
class ManualNewsService
{
    // How long a manually-added item keeps counting toward the news sub-score. Without this,
    // something added once would silently keep influencing every future analysis of that ticker
    // forever, long after it stopped being relevant.
    private const RELEVANCE_DAYS = 14;

    public function __construct(
        private SentimentService $sentiment,
    ) {}

    public function store(string $ticker, string $market, string $text, string $source): ManualNewsItem
    {
        return ManualNewsItem::create([
            'ticker' => strtoupper($ticker),
            'market' => $market,
            'text' => $text,
            'source' => $source,
            'sentiment_score' => $this->sentiment->scoreHeadline($text),
        ]);
    }

    /** @return array<int, array{title: string, url: null, source: string, publishedAt: string, sentimentScore: float}> */
    public function recentArticlesFor(string $ticker, string $market): array
    {
        $items = ManualNewsItem::where('ticker', strtoupper($ticker))
            ->where('market', $market)
            ->where('created_at', '>=', now()->subDays(self::RELEVANCE_DAYS))
            ->orderByDesc('created_at')
            ->get();

        return $items->map(fn (ManualNewsItem $item) => [
            'title' => $item->text,
            'url' => null,
            'source' => 'Input manual',
            'publishedAt' => $item->created_at->toIso8601String(),
            'sentimentScore' => $item->sentiment_score,
        ])->all();
    }

    public function forTicker(string $ticker, string $market)
    {
        return ManualNewsItem::where('ticker', strtoupper($ticker))
            ->where('market', $market)
            ->orderByDesc('created_at')
            ->get();
    }
}
