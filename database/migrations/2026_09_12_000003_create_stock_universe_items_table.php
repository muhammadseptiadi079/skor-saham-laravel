<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Curated tickers the gainers screener scans in addition to the user's watchlist (e.g. LQ45
// constituents for IDX, blue-chip names for global) — kept small and seeded, since scanning is
// bounded by the free-tier API rate limits (see StockAnalysisService / RefreshStockScores).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_universe_items', function (Blueprint $table) {
            $table->id();
            $table->string('ticker');
            $table->string('market'); // idx | global
            $table->string('name')->nullable();
            $table->string('category')->nullable(); // e.g. lq45, bluechip
            $table->timestamps();
            $table->unique(['ticker', 'market']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_universe_items');
    }
};
