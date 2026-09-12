<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipo_listings', function (Blueprint $table) {
            $table->id();
            $table->string('market'); // idx | global
            $table->string('ticker')->nullable();
            $table->string('company_name');
            $table->date('ipo_date')->nullable();
            $table->string('price_range')->nullable();
            $table->string('status')->nullable(); // e.g. upcoming, listed
            $table->string('source'); // alpha_vantage | idx_scrape
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->index(['market', 'ipo_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipo_listings');
    }
};
