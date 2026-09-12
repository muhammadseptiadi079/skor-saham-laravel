<?php

namespace Tests\Feature;

use App\Services\NationalThemeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NationalThemeServiceTest extends TestCase
{
    private function fakeNewsCount(int $count): void
    {
        $items = str_repeat(
            '<item><title>Berita</title><link>https://example.com/a</link>'.
            '<source>Detik</source><pubDate>Mon, 01 Jan 2024 00:00:00 GMT</pubDate></item>',
            $count
        );

        Http::fake([
            'https://news.google.com/rss/search*' => Http::response(
                "<?xml version=\"1.0\"?><rss><channel>{$items}</channel></rss>",
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);
    }

    public function test_returns_empty_when_sector_is_null(): void
    {
        $this->fakeNewsCount(10);
        $service = app(NationalThemeService::class);

        $this->assertSame([], $service->activeThemesForSector(null, 'idx'));
    }

    public function test_returns_empty_for_global_market(): void
    {
        $this->fakeNewsCount(10);
        $service = app(NationalThemeService::class);

        $this->assertSame([], $service->activeThemesForSector('Kesehatan', 'global'));
    }

    public function test_returns_empty_when_sector_does_not_match_any_theme(): void
    {
        $this->fakeNewsCount(10);
        $service = app(NationalThemeService::class);

        $this->assertSame([], $service->activeThemesForSector('Teknologi', 'idx'));
    }

    public function test_theme_active_when_enough_articles_found_for_matching_sector(): void
    {
        $this->fakeNewsCount(5);
        $service = app(NationalThemeService::class);

        $result = $service->activeThemesForSector('Kesehatan', 'idx');

        $this->assertCount(1, $result);
        $this->assertStringContainsString('kebakaran hutan', $result[0]['note']);
    }

    public function test_theme_inactive_when_too_few_articles_found(): void
    {
        $this->fakeNewsCount(1);
        $service = app(NationalThemeService::class);

        $this->assertSame([], $service->activeThemesForSector('Kesehatan', 'idx'));
    }

    public function test_result_is_cached_and_does_not_refetch(): void
    {
        $this->fakeNewsCount(5);
        $service = app(NationalThemeService::class);

        $service->activeThemesForSector('Kesehatan', 'idx');

        Http::fake([
            'https://news.google.com/rss/search*' => Http::response('', 500),
        ]);

        $result = $service->activeThemesForSector('Kesehatan', 'idx');

        $this->assertCount(1, $result);
    }

    public function test_multiple_sectors_can_match_different_themes(): void
    {
        $this->fakeNewsCount(5);
        $service = app(NationalThemeService::class);

        $miningResult = $service->activeThemesForSector('Pertambangan', 'idx');
        $retailResult = $service->activeThemesForSector('Konsumer & Ritel', 'idx');

        $this->assertCount(1, $miningResult);
        $this->assertStringContainsString('komoditas', $miningResult[0]['note']);
        // Konsumer & Ritel matches two themes (karhutla_asap + kemarau_panjang).
        $this->assertCount(2, $retailResult);
    }
}
