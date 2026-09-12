<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_history', function (Blueprint $table) {
            $table->id();
            $table->string('ticker');
            $table->string('market'); // idx | global
            $table->string('name')->nullable();
            $table->string('currency', 3)->nullable();
            $table->float('longterm_score')->nullable();
            $table->string('longterm_label')->nullable();
            $table->float('trading_score')->nullable();
            $table->string('trading_label')->nullable();
            $table->json('sub_scores')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->index(['ticker', 'market', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_history');
    }
};
