<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

// Real commodity-price momentum for sectors whose stocks are commodity-linked — a quantitative
// companion to NationalThemeService's "harga_komoditas" theme (which only checks whether
// commodities are trending in the news, not how much prices actually moved). Alpha Vantage's free
// commodity endpoints don't cover Indonesia's actual biggest exports (coal, nickel, CPO) — WTI
// crude and COPPER are used as the closest available proxies, which is an honest limitation
// stated in the note itself, not a precise match. Purely informational: ScoringEngine never
// scores this, since how much a commodity move actually hits any one company's earnings varies
// hugely (contracts, hedging, cost structure) — same reasoning NationalThemeService already uses
// for its own commodity theme.
class CommodityPriceService
{
    /** @var array<string, array{function: string, label: string}> */
    private const SECTOR_COMMODITIES = [
        'Energi' => ['function' => 'WTI', 'label' => 'minyak mentah WTI'],
        'Pertambangan' => ['function' => 'COPPER', 'label' => 'tembaga (proksi logam dasar)'],
    ];

    // A month of commodity data doesn't meaningfully change within a day — cached generously so
    // every analysis in a matching sector doesn't re-hit Alpha Vantage's 25-requests/day limit.
    private const CACHE_HOURS = 24;

    // "Recent trend" = latest data point vs this many monthly points back.
    private const LOOKBACK_MONTHS = 3;

    // Below this, the move is treated as noise rather than something worth surfacing.
    private const MEANINGFUL_MOVE_THRESHOLD = 0.05;

    public function __construct(private AlphaVantageService $alphaVantage) {}

    public function contextNoteFor(?string $sector): ?string
    {
        if (! $sector || ! isset(self::SECTOR_COMMODITIES[$sector])) {
            return null;
        }

        $config = self::SECTOR_COMMODITIES[$sector];
        $points = $this->cachedPoints($config['function']);
        if (! $points || count($points) <= self::LOOKBACK_MONTHS) {
            return null;
        }

        $latest = $points[0]['value'];
        $past = $points[self::LOOKBACK_MONTHS]['value'];
        if ($past == 0.0) {
            return null;
        }

        $changePct = ($latest - $past) / $past;
        if (abs($changePct) < self::MEANINGFUL_MOVE_THRESHOLD) {
            return null;
        }

        $arah = $changePct > 0 ? 'naik' : 'turun';
        $pct = number_format(abs($changePct) * 100, 1);

        return "Harga {$config['label']} {$arah} {$pct}% dalam ".self::LOOKBACK_MONTHS.' bulan terakhir — bisa memengaruhi '.
            "emiten sektor {$sector}, tapi besarannya beda-beda per perusahaan (tergantung kontrak jual, lindung nilai, ".
            'biaya produksi) — bukan sinyal otomatis untuk saham ini secara spesifik. Catatan: '.
            "{$config['label']} dipakai sebagai proksi terdekat yang tersedia gratis, bukan harga komoditas ekspor ".
            'Indonesia yang persis sama (data batu bara/nikel/CPO tidak tersedia di sumber gratis yang dipakai aplikasi ini).';
    }

    /** @return array<int, array{date: string, value: float}>|null */
    private function cachedPoints(string $function): ?array
    {
        return Cache::remember("commodity_price:{$function}", now()->addHours(self::CACHE_HOURS), function () use ($function) {
            try {
                return $this->alphaVantage->getCommodity($function);
            } catch (\Throwable $e) {
                Log::warning("Commodity price fetch failed ({$function}): ".$e->getMessage());

                return null;
            }
        });
    }
}
