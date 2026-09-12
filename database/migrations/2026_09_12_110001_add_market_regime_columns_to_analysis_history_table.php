<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets the backtest tell "this label was right because the whole market was up" apart from
// "this label was right for its own reasons" — see BacktestService's regime classification.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_history', function (Blueprint $table) {
            $table->double('benchmark_price_at_generation')->nullable()->after('price_at_generation');
            $table->string('market_regime')->nullable()->after('outcome_correct');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_history', function (Blueprint $table) {
            $table->dropColumn(['benchmark_price_at_generation', 'market_regime']);
        });
    }
};
