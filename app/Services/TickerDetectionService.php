<?php

namespace App\Services;

use App\Models\StockUniverseItem;
use App\Models\WatchlistItem;
use App\Support\SectorStarterPacks;

// Lets the manual-news upload flow work backwards from the usual "pick a ticker, then attach news
// to it" form: the user just pastes/uploads the news, and this scans the text against every
// ticker/company name the app already knows about (the user's own watchlist, the curated screener
// universe, and the sector starter packs) to figure out which stock(s) it's about. Deliberately
// plain string matching, not ML — same "every step is explainable" philosophy as ScoringEngine: a
// match is either a ticker code appearing as its own uppercase word, or a company name (with the
// "Tbk"/"Inc"/etc. suffix stripped) appearing as a substring. No coverage beyond names this app has
// already seen — a brand-new company nobody has watchlisted yet simply won't be found, and the
// caller needs a manual-entry fallback for that case.
class TickerDetectionService
{
    private const NAME_SUFFIXES = ['tbk', 'inc', 'incorporated', 'corp', 'corporation', 'co', 'ltd', 'plc', 'persero'];

    // Below this length a "core" company name (after stripping suffixes) is too generic to trust
    // as a substring match — e.g. a single leftover word like "Bank" would match almost anything.
    private const MIN_NAME_LENGTH = 4;

    /** @return array<int, array{ticker: string, market: string, name: string|null}> */
    public function candidates(): array
    {
        $known = [];

        // Order matters: earlier sources win when the same ticker/market appears more than once,
        // since the watchlist's name (fetched from the actual API response when it was added) is
        // more likely accurate than the static starter-pack list.
        foreach (WatchlistItem::all(['ticker', 'market', 'name']) as $w) {
            $known[$this->key($w->ticker, $w->market)] = ['ticker' => $w->ticker, 'market' => $w->market, 'name' => $w->name];
        }
        foreach (StockUniverseItem::all(['ticker', 'market', 'name']) as $u) {
            $this->fillIfMissingName($known, $u->ticker, $u->market, $u->name);
        }
        foreach (SectorStarterPacks::all() as $s) {
            $this->fillIfMissingName($known, $s['ticker'], 'idx', $s['name']);
        }

        return array_values($known);
    }

    /** @return array<int, array{ticker: string, market: string, name: string|null}> */
    public function detect(string $text): array
    {
        return array_values(array_filter(
            $this->candidates(),
            fn (array $c) => $this->tickerMentioned($text, $c['ticker']) || ($c['name'] && $this->nameMentioned($text, $c['name']))
        ));
    }

    private function key(string $ticker, string $market): string
    {
        return "{$market}:{$ticker}";
    }

    private function fillIfMissingName(array &$known, string $ticker, string $market, ?string $name): void
    {
        $key = $this->key($ticker, $market);
        if (! isset($known[$key])) {
            $known[$key] = ['ticker' => $ticker, 'market' => $market, 'name' => $name];

            return;
        }
        if (! $known[$key]['name'] && $name) {
            $known[$key]['name'] = $name;
        }
    }

    // Case-sensitive on purpose: real news writes stock codes in full caps ("BBCA naik 2%"), so
    // requiring exact case avoids a 4-letter ticker that happens to spell an ordinary lowercase
    // word (e.g. "goto") from matching plain text that isn't actually about the stock.
    private function tickerMentioned(string $text, string $ticker): bool
    {
        return (bool) preg_match('/\b'.preg_quote($ticker, '/').'\b/', $text);
    }

    private function nameMentioned(string $text, string $name): bool
    {
        $core = $this->coreName($name);
        if (mb_strlen($core) < self::MIN_NAME_LENGTH) {
            return false;
        }

        return mb_stripos($text, $core) !== false;
    }

    private function coreName(string $name): string
    {
        $words = array_filter(
            preg_split('/\s+/', trim($name)),
            fn ($w) => ! in_array(mb_strtolower(rtrim($w, '.')), self::NAME_SUFFIXES, true)
        );

        return trim(implode(' ', $words));
    }
}
