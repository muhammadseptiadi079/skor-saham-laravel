<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table) {
            $table->string('sector')->default('Lainnya')->after('name');
            $table->boolean('is_favorite')->default(false)->after('sector');
        });
    }

    public function down(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table) {
            $table->dropColumn(['sector', 'is_favorite']);
        });
    }
};
