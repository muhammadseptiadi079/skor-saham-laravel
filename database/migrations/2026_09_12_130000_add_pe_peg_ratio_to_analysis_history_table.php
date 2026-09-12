<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Persists the raw P/E and PEG ratio at generation time so SectorValuationService can compare a
// stock's valuation against other watchlist stocks in the same sector later, without needing to
// re-fetch or parse them back out of the fundamentals notes text.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_history', function (Blueprint $table) {
            $table->double('pe_ratio')->nullable()->after('sub_scores');
            $table->double('peg_ratio')->nullable()->after('pe_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_history', function (Blueprint $table) {
            $table->dropColumn(['pe_ratio', 'peg_ratio']);
        });
    }
};
