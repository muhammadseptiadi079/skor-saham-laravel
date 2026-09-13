<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Extends SectorValuationService's peer comparison beyond P/E and PEG: profit margin and ROE let
// it say "cheaper AND more/less profitable than sector peers," not just "cheaper."
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_history', function (Blueprint $table) {
            $table->double('profit_margin')->nullable()->after('news_article_count');
            $table->double('roe')->nullable()->after('profit_margin');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_history', function (Blueprint $table) {
            $table->dropColumn(['profit_margin', 'roe']);
        });
    }
};
