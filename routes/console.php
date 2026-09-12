<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keeps the gainers screener and IPO listing fresh without hitting external APIs on every
// request. Staggered so they don't compete for the same Alpha Vantage daily quota at once.
Schedule::command('stocks:refresh-scores')->dailyAt('03:00');
Schedule::command('stocks:refresh-ipo')->dailyAt('04:00');
Schedule::command('stocks:evaluate-backtest')->dailyAt('05:00');
