<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Persists how many news articles fed into the news sub-score at generation time, so
// NewsVolumeService can compare a stock's current news volume against its own recent history —
// "normal" article count varies too much by company size for a single absolute threshold to work.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_history', function (Blueprint $table) {
            $table->unsignedInteger('news_article_count')->nullable()->after('peg_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_history', function (Blueprint $table) {
            $table->dropColumn('news_article_count');
        });
    }
};
