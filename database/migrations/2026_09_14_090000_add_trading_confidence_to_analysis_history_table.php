<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets /api/accuracy check whether ScoringEngine's confidence score (Tinggi/Sedang/Rendah — how
// much the sub-scores agreed on the "trading" call) actually tracks with real accuracy, instead of
// being a number nobody ever validates.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_history', function (Blueprint $table) {
            $table->string('trading_confidence')->nullable()->after('trading_label');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_history', function (Blueprint $table) {
            $table->dropColumn('trading_confidence');
        });
    }
};
