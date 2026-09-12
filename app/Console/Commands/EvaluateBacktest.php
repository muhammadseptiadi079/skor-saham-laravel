<?php

namespace App\Console\Commands;

use App\Services\BacktestService;
use Illuminate\Console\Command;

class EvaluateBacktest extends Command
{
    protected $signature = 'stocks:evaluate-backtest {--min-age-days=28 : Only grade analyses at least this many calendar days old (~20 trading days)} {--limit=50 : Max analyses to check per run}';

    protected $description = 'Grade past analyses against what actually happened to the price, to measure the scoring\'s real accuracy';

    public function handle(BacktestService $backtest): int
    {
        $result = $backtest->evaluate((int) $this->option('min-age-days'), (int) $this->option('limit'));
        $this->info("Evaluated {$result['evaluated']} analyses, skipped {$result['skipped']} (price fetch failed, will retry next run).");

        return self::SUCCESS;
    }
}
