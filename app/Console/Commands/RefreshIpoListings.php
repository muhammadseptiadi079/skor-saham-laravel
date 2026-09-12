<?php

namespace App\Console\Commands;

use App\Models\IpoListing;
use App\Services\AlphaVantageService;
use App\Services\IdxIpoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshIpoListings extends Command
{
    protected $signature = 'stocks:refresh-ipo';

    protected $description = 'Refresh the cached IPO listings (global via Alpha Vantage, IDX best-effort) used by /api/ipo';

    public function handle(AlphaVantageService $alphaVantage, IdxIpoService $idxIpo): int
    {
        $now = now();

        try {
            $globalIpos = $alphaVantage->getIpoCalendar();
            $this->replaceForSource('alpha_vantage', 'global', $globalIpos, $now);
            $this->info('Global IPO calendar: '.count($globalIpos).' entries.');
        } catch (\Throwable $e) {
            $this->error('Alpha Vantage IPO calendar fetch failed: '.$e->getMessage());
            Log::warning('IPO calendar (global) fetch failed: '.$e->getMessage());
        }

        $idxIpos = $idxIpo->getRecentIpos();
        $this->replaceForSource('idx_scrape', 'idx', $idxIpos, $now);
        $this->info('IDX IPO listings: '.count($idxIpos).' entries'.(count($idxIpos) === 0 ? ' (source may need adjusting — see IdxIpoService).' : '.'));

        return self::SUCCESS;
    }

    private function replaceForSource(string $source, string $market, array $rows, \DateTimeInterface $now): void
    {
        IpoListing::where('source', $source)->delete();

        foreach ($rows as $row) {
            IpoListing::create([
                'market' => $market,
                'ticker' => $row['ticker'] ?? null,
                'company_name' => $row['companyName'] ?? ($row['ticker'] ?? 'Unknown'),
                'ipo_date' => $row['ipoDate'] ?? null,
                'price_range' => $row['priceRange'] ?? null,
                'status' => $row['status'] ?? null,
                'source' => $source,
                'fetched_at' => $now,
            ]);
        }
    }
}
