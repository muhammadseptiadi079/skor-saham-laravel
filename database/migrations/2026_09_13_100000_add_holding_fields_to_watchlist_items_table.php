<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Turns a favorited ("already bought") watchlist item into a real, if lightweight, portfolio
// holding — how many shares, and the average price paid. Kept on watchlist_items rather than a
// separate holdings table since a holding IS a favorited watchlist item; a full buy/sell ledger
// (for true FIFO/weighted-average cost accounting across multiple trades) would be a much bigger
// feature than "let me see my rough unrealized P&L."
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table) {
            $table->double('shares_owned')->nullable()->after('is_favorite');
            $table->double('avg_buy_price')->nullable()->after('shares_owned');
        });
    }

    public function down(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table) {
            $table->dropColumn(['shares_owned', 'avg_buy_price']);
        });
    }
};
