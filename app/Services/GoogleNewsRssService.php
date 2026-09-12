<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

// Google News RSS search — free, no key, but headlines only (no article body, no official sentiment).
class GoogleNewsRssService
{
    public function getNews(string $query, string $lang = 'id', string $country = 'ID'): array
    {
        $url = 'https://news.google.com/rss/search';
        $xml = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get($url, [
                'q' => $query,
                'hl' => $lang,
                'gl' => $country,
                'ceid' => "{$country}:{$lang}",
            ])->body();

        $prevSetting = libxml_use_internal_errors(true);
        $rss = simplexml_load_string($xml);
        libxml_use_internal_errors($prevSetting);

        if ($rss === false || ! isset($rss->channel->item)) {
            return [];
        }

        $items = [];
        $count = 0;
        foreach ($rss->channel->item as $item) {
            if ($count >= 25) {
                break;
            }
            $items[] = [
                'title' => (string) $item->title,
                'url' => (string) $item->link,
                'source' => isset($item->source) ? (string) $item->source : null,
                'publishedAt' => (string) $item->pubDate,
            ];
            $count++;
        }

        return $items;
    }
}
