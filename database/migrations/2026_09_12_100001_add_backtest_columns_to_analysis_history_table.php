<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Powers the "accuracy" backtest: was the label's predicted direction right, N trading days later?
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_history', function (Blueprint $table) {
            $table->double('price_at_generation')->nullable()->after('sub_scores');
            $table->integer('evaluation_horizon_days')->nullable()->after('price_at_generation');
            $table->timestamp('evaluated_at')->nullable()->after('evaluation_horizon_days');
            $table->double('forward_return')->nullable()->after('evaluated_at');
            $table->boolean('outcome_correct')->nullable()->after('forward_return');
            $table->index('evaluated_at');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_history', function (Blueprint $table) {
            $table->dropColumn(['price_at_generation', 'evaluation_horizon_days', 'evaluated_at', 'forward_return', 'outcome_correct']);
        });
    }
};
