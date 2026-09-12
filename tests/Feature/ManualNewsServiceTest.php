<?php

namespace Tests\Feature;

use App\Models\ManualNewsItem;
use App\Services\ManualNewsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualNewsServiceTest extends TestCase
{
    use RefreshDatabase;

    private ManualNewsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ManualNewsService::class);
    }

    public function test_store_scores_the_text_through_the_sentiment_dictionary(): void
    {
        $item = $this->service->store('bbca', 'idx', 'Laba BBCA melonjak tahun ini', 'typed');

        $this->assertSame('BBCA', $item->ticker);
        $this->assertGreaterThan(0, $item->sentiment_score);
        $this->assertDatabaseHas('manual_news_items', ['ticker' => 'BBCA', 'source' => 'typed']);
    }

    public function test_recent_articles_for_shapes_items_like_a_news_article(): void
    {
        $this->service->store('BBCA', 'idx', 'Laba BBCA melonjak', 'typed');

        $articles = $this->service->recentArticlesFor('BBCA', 'idx');

        $this->assertCount(1, $articles);
        $this->assertSame('Laba BBCA melonjak', $articles[0]['title']);
        $this->assertNull($articles[0]['url']);
        $this->assertSame('Input manual', $articles[0]['source']);
        $this->assertGreaterThan(0, $articles[0]['sentimentScore']);
    }

    public function test_recent_articles_for_excludes_items_older_than_14_days(): void
    {
        $item = ManualNewsItem::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'text' => 'Berita lama',
            'source' => 'typed', 'sentiment_score' => 0.5,
        ]);
        $item->created_at = now()->subDays(20);
        $item->save();

        $articles = $this->service->recentArticlesFor('BBCA', 'idx');

        $this->assertCount(0, $articles);
    }

    public function test_recent_articles_for_only_matches_same_ticker_and_market(): void
    {
        $this->service->store('BBCA', 'idx', 'Berita BBCA', 'typed');
        $this->service->store('BBCA', 'global', 'Different market', 'typed');
        $this->service->store('AAPL', 'idx', 'Different ticker', 'typed');

        $articles = $this->service->recentArticlesFor('BBCA', 'idx');

        $this->assertCount(1, $articles);
        $this->assertSame('Berita BBCA', $articles[0]['title']);
    }

    public function test_for_ticker_returns_all_items_regardless_of_age(): void
    {
        $item = ManualNewsItem::create([
            'ticker' => 'BBCA', 'market' => 'idx', 'text' => 'Berita lama',
            'source' => 'typed', 'sentiment_score' => 0.5,
        ]);
        $item->created_at = now()->subDays(20);
        $item->save();
        $this->service->store('BBCA', 'idx', 'Berita baru', 'typed');

        $items = $this->service->forTicker('BBCA', 'idx');

        $this->assertCount(2, $items);
    }
}
