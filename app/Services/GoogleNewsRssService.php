<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

// Google News RSS search — free, no key, but headlines only (no article body, no official sentiment).
class GoogleNewsRssService
{
    public static function headers(): array
    {
        return ['User-Agent' => 'Mozilla/5.0'];
    }

    public function url(): string
    {
        return 'https://news.google.com/rss/search';
    }

    public function query(string $query, string $lang = 'id', string $country = 'ID'): array
    {
        return ['q' => $query, 'hl' => $lang, 'gl' => $country, 'ceid' => "{$country}:{$lang}"];
    }

    public function getNews(string $query, string $lang = 'id', string $country = 'ID'): array
    {
        $xml = Http::withHeaders(self::headers())->get($this->url(), $this->query($query, $lang, $country))->body();

        return $this->parseNewsXml($xml);
    }

    public function parseNewsXml(string $xml): array
    {
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
