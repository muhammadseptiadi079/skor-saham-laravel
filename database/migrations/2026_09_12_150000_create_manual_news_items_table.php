<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets the user manually add a news item (typed, or OCR'd from a screenshot) for a ticker — for
// news the automatic Google News RSS / Alpha Vantage News Sentiment feeds missed (e.g. something
// seen on Stockbit or a broker app). Scored through the same SentimentService dictionary used for
// automatic news, then merged into that ticker's news sub-score on future analyses.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_news_items', function (Blueprint $table) {
            $table->id();
            $table->string('ticker');
            $table->string('market'); // idx | global
            $table->text('text');
            $table->string('source'); // typed | screenshot
            $table->float('sentiment_score');
            $table->timestamps();
            $table->index(['ticker', 'market', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_news_items');
    }
};
