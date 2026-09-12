<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

// Surfaces national/macro news themes (disasters, weather, commodity moves) that are trending
// right now and heuristically maps them to sectors that plausibly benefit or suffer — e.g. a
// forest-fire haze event coinciding with more mask/respiratory-drug and air-purifier demand.
// This is deliberately the noisiest, most speculative signal in the app: the theme→sector mapping
// below is a hand-picked guess, not a verified causal or financial link, and "more news mentions"
// only proxies "this is being talked about" — not "this specific company is affected." It is
// never scored into ScoringEngine's weighted average, only ever a "go look into this yourself"
// context note. IDX-only for now: the example themes (karhutla/kabut asap, kemarau panjang) are
// Indonesia-specific, and a useful equivalent list for global markets hasn't been curated yet.
class NationalThemeService
{
    // A theme needs at least this many recent headlines to count as "actively in the news" rather
    // than background noise — an arbitrary but conservative bar, tunable in one place.
    private const MIN_ARTICLES = 3;

    // Sector values must match App\Support\Sectors::ALL exactly.
    private const THEMES = [
        'karhutla_asap' => [
            'query' => 'kebakaran hutan kabut asap',
            'sectors' => ['Kesehatan', 'Konsumer & Ritel'],
            'note' => 'Lagi ramai berita kebakaran hutan/kabut asap — secara tematik permintaan masker, '.
                'obat pernapasan, dan alat pembersih udara (air purifier) biasanya ikut naik. Ini sentimen '.
                'tema sektor secara umum, bukan bukti dampak langsung ke laporan keuangan saham ini — '.
                'cek sendiri apakah perusahaannya memang berjualan produk yang relevan.',
        ],
        'kemarau_panjang' => [
            'query' => 'kemarau panjang kekeringan El Nino Indonesia',
            'sectors' => ['Konsumer & Ritel', 'Energi'],
            'note' => 'Lagi ramai berita kemarau panjang/kekeringan — secara tematik permintaan AC/pendingin '.
                'ruangan dan konsumsi listrik untuk pendinginan cenderung naik, sebaliknya sektor yang '.
                'bergantung pasokan air (pertanian, PDAM) bisa tertekan. Sentimen tematik, bukan proyeksi '.
                'laporan keuangan spesifik saham ini.',
        ],
        'harga_komoditas' => [
            'query' => 'harga batu bara nikel CPO naik',
            'sectors' => ['Pertambangan', 'Energi'],
            'note' => 'Lagi ramai berita kenaikan harga komoditas (batu bara/nikel/CPO) — bisa menguntungkan '.
                'emiten penjual komoditas terkait, tapi besaran dampaknya beda-beda per perusahaan '.
                '(tergantung kontrak jual, lindung nilai, biaya produksi) — bukan sinyal otomatis '.
                '"beli semua saham sektor ini".',
        ],
    ];

    public function __construct(
        private GoogleNewsRssService $googleNews,
    ) {}

    /** @return array<int, array{note: string}> */
    public function activeThemesForSector(?string $sector, string $market): array
    {
        if (! $sector || $market !== 'idx') {
            return [];
        }

        $active = [];
        foreach (self::THEMES as $key => $theme) {
            if (! in_array($sector, $theme['sectors'], true)) {
                continue;
            }
            if ($this->isThemeActive($key, $theme['query'])) {
                $active[] = ['note' => $theme['note']];
            }
        }

        return $active;
    }

    // Cached across all tickers/requests — a theme's news volume doesn't change second-to-second,
    // and this keeps the app from hitting Google News RSS once per analysis for the same query.
    private function isThemeActive(string $key, string $query): bool
    {
        return Cache::remember("national_theme_active_{$key}", now()->addHours(6), function () use ($query) {
            $articles = $this->googleNews->getNews($query);

            return count($articles) >= self::MIN_ARTICLES;
        });
    }
}
