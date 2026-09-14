<?php

namespace Tests\Feature;

use App\Models\ManualNewsItem;
use App\Models\WatchlistItem;
use App\Services\ImageTextExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

// The image-upload path is tested against a bound fake ImageTextExtractionService rather than the
// real tesseract binary — this controller's job is to orchestrate (validate, call the extractor,
// score, persist), not to prove OCR accuracy (that's ImageTextExtractionServiceTest's job, which
// skips itself when tesseract isn't installed). Keeping this suite free of that dependency means
// it runs the same everywhere regardless of whether the OCR binary is present.
class ManualNewsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_submit_typed_news_and_it_gets_scored(): void
    {
        $response = $this->postJson('/api/news/manual', [
            'ticker' => 'bbca',
            'market' => 'idx',
            'text' => 'Laba BBCA melonjak tahun ini',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('ticker', 'BBCA');
        $response->assertJsonPath('source', 'typed');
        $this->assertGreaterThan(0, $response->json('sentimentScore'));
        $this->assertContains('laba', $response->json('matchedKeywords.positive'));
        $this->assertDatabaseCount('manual_news_items', 1);
    }

    public function test_rejects_when_neither_text_nor_image_given(): void
    {
        $this->postJson('/api/news/manual', ['ticker' => 'BBCA', 'market' => 'idx'])
            ->assertStatus(422);
    }

    public function test_rejects_invalid_market(): void
    {
        $this->postJson('/api/news/manual', ['ticker' => 'BBCA', 'market' => 'moon', 'text' => 'Halo'])
            ->assertStatus(422);
    }

    public function test_can_submit_a_screenshot_and_extracted_text_gets_scored(): void
    {
        $fake = Mockery::mock(ImageTextExtractionService::class);
        $fake->shouldReceive('extractText')->once()->andReturn('Saham ANTM melonjak setelah laba naik');
        $this->app->instance(ImageTextExtractionService::class, $fake);

        $image = UploadedFile::fake()->image('screenshot.png');

        $response = $this->postJson('/api/news/manual', [
            'ticker' => 'ANTM',
            'market' => 'idx',
            'image' => $image,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('source', 'screenshot');
        $response->assertJsonPath('text', 'Saham ANTM melonjak setelah laba naik');
        $this->assertGreaterThan(0, $response->json('sentimentScore'));
    }

    public function test_returns_422_when_ocr_fails(): void
    {
        $fake = Mockery::mock(ImageTextExtractionService::class);
        $fake->shouldReceive('extractText')->once()->andThrow(new \RuntimeException('OCR (tesseract) tidak tersedia di server ini.'));
        $this->app->instance(ImageTextExtractionService::class, $fake);

        $image = UploadedFile::fake()->image('screenshot.png');

        $response = $this->postJson('/api/news/manual', [
            'ticker' => 'ANTM',
            'market' => 'idx',
            'image' => $image,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'ocr_failed');
    }

    public function test_returns_422_when_ocr_extracts_no_text(): void
    {
        $fake = Mockery::mock(ImageTextExtractionService::class);
        $fake->shouldReceive('extractText')->once()->andReturn('');
        $this->app->instance(ImageTextExtractionService::class, $fake);

        $image = UploadedFile::fake()->image('screenshot.png');

        $response = $this->postJson('/api/news/manual', [
            'ticker' => 'ANTM',
            'market' => 'idx',
            'image' => $image,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'ocr_empty');
        $this->assertDatabaseCount('manual_news_items', 0);
    }

    public function test_detect_auto_resolves_a_single_candidate_from_typed_text(): void
    {
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'name' => 'Bank Central Asia Tbk']);

        $response = $this->postJson('/api/news/manual/detect', [
            'text' => 'BBCA melaporkan laba bersih naik tahun ini',
        ]);

        $response->assertOk();
        $response->assertJsonPath('text', 'BBCA melaporkan laba bersih naik tahun ini');
        $response->assertJsonCount(1, 'candidates');
        $response->assertJsonPath('candidates.0.ticker', 'BBCA');
        $response->assertJsonPath('candidates.0.market', 'idx');
    }

    public function test_detect_returns_several_candidates_when_text_is_ambiguous(): void
    {
        WatchlistItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'name' => 'Bank Central Asia Tbk']);
        WatchlistItem::create(['ticker' => 'BBRI', 'market' => 'idx', 'name' => 'Bank Rakyat Indonesia Tbk']);

        $response = $this->postJson('/api/news/manual/detect', [
            'text' => 'Saham perbankan BBCA dan BBRI kompak menguat',
        ]);

        $response->assertOk();
        $tickers = collect($response->json('candidates'))->pluck('ticker');
        $this->assertTrue($tickers->contains('BBCA'));
        $this->assertTrue($tickers->contains('BBRI'));
    }

    public function test_detect_returns_empty_candidates_for_unrecognized_text(): void
    {
        $response = $this->postJson('/api/news/manual/detect', [
            'text' => 'Cuaca hari ini cerah berawan di seluruh Jakarta',
        ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'candidates');
    }

    public function test_detect_works_with_a_screenshot_via_ocr(): void
    {
        WatchlistItem::create(['ticker' => 'ANTM', 'market' => 'idx', 'name' => 'Aneka Tambang Tbk']);

        $fake = Mockery::mock(ImageTextExtractionService::class);
        $fake->shouldReceive('extractText')->once()->andReturn('Saham ANTM melonjak setelah laba naik');
        $this->app->instance(ImageTextExtractionService::class, $fake);

        $response = $this->postJson('/api/news/manual/detect', [
            'image' => UploadedFile::fake()->image('screenshot.png'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('text', 'Saham ANTM melonjak setelah laba naik');
        $response->assertJsonPath('candidates.0.ticker', 'ANTM');
    }

    public function test_detect_returns_422_when_ocr_extracts_no_text(): void
    {
        $fake = Mockery::mock(ImageTextExtractionService::class);
        $fake->shouldReceive('extractText')->once()->andReturn('');
        $this->app->instance(ImageTextExtractionService::class, $fake);

        $response = $this->postJson('/api/news/manual/detect', [
            'image' => UploadedFile::fake()->image('screenshot.png'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'ocr_empty');
    }

    public function test_can_list_items_for_a_ticker(): void
    {
        ManualNewsItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'text' => 'A', 'source' => 'typed', 'sentiment_score' => 0.5]);
        ManualNewsItem::create(['ticker' => 'AAPL', 'market' => 'global', 'text' => 'B', 'source' => 'typed', 'sentiment_score' => 0.5]);

        $response = $this->getJson('/api/news/manual?ticker=BBCA&market=idx');

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    public function test_can_delete_an_item(): void
    {
        $item = ManualNewsItem::create(['ticker' => 'BBCA', 'market' => 'idx', 'text' => 'A', 'source' => 'typed', 'sentiment_score' => 0.5]);

        $this->deleteJson("/api/news/manual/{$item->id}")->assertOk();

        $this->assertDatabaseCount('manual_news_items', 0);
    }
}
