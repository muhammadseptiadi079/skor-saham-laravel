<?php

namespace Tests\Feature;

use App\Services\CommodityPriceService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommodityPriceServiceTest extends TestCase
{
    // $valuesNewestFirst[0] is the latest month, $valuesNewestFirst[1] one month before that, etc.
    // — dates are generated in matching descending order so parseCommodity()'s own newest-first
    // sort is a no-op here and index access in the assertions lines up with the input as written.
    private function fakeCommodity(array $valuesNewestFirst): void
    {
        $count = count($valuesNewestFirst);
        $data = [];
        foreach ($valuesNewestFirst as $i => $v) {
            $data[] = ['date' => sprintf('2024-%02d-01', $count - $i), 'value' => $v];
        }

        Http::fake([
            'alphavantage.co/*' => Http::response(['data' => $data]),
        ]);
    }

    public function test_returns_null_for_sector_with_no_commodity_mapping(): void
    {
        $this->fakeCommodity([100, 100, 100, 100]);

        $result = app(CommodityPriceService::class)->contextNoteFor('Teknologi');

        $this->assertNull($result);
    }

    public function test_returns_null_for_null_sector(): void
    {
        $result = app(CommodityPriceService::class)->contextNoteFor(null);

        $this->assertNull($result);
    }

    public function test_notes_a_meaningful_price_increase_for_energi_sector(): void
    {
        // Index 0 = latest (Jan), index 3 = 3 months back (Apr) after sort by date desc — set up
        // so latest > 3-months-back by more than the 5% threshold.
        $this->fakeCommodity([80, 78, 75, 70, 68]);

        $result = app(CommodityPriceService::class)->contextNoteFor('Energi');

        $this->assertNotNull($result);
        $this->assertStringContainsString('minyak mentah WTI', $result);
        $this->assertStringContainsString('naik', $result);
    }

    public function test_notes_a_meaningful_price_decrease_for_pertambangan_sector(): void
    {
        $this->fakeCommodity([70, 75, 80, 85, 90]);

        $result = app(CommodityPriceService::class)->contextNoteFor('Pertambangan');

        $this->assertNotNull($result);
        $this->assertStringContainsString('tembaga', $result);
        $this->assertStringContainsString('turun', $result);
    }

    public function test_returns_null_when_move_is_too_small_to_be_meaningful(): void
    {
        $this->fakeCommodity([100, 100.5, 101, 101.5, 102]);

        $result = app(CommodityPriceService::class)->contextNoteFor('Energi');

        $this->assertNull($result);
    }

    public function test_returns_null_when_not_enough_history(): void
    {
        $this->fakeCommodity([80, 70]);

        $result = app(CommodityPriceService::class)->contextNoteFor('Energi');

        $this->assertNull($result);
    }

    public function test_returns_null_when_endpoint_fails(): void
    {
        Http::fake(['alphavantage.co/*' => Http::response('', 500)]);

        $result = app(CommodityPriceService::class)->contextNoteFor('Energi');

        $this->assertNull($result);
    }

    public function test_result_is_cached_and_does_not_refetch(): void
    {
        $this->fakeCommodity([80, 78, 75, 70, 68]);

        $service = app(CommodityPriceService::class);
        $first = $service->contextNoteFor('Energi');

        Http::fake(['alphavantage.co/*' => Http::response('', 500)]);
        $second = $service->contextNoteFor('Energi');

        $this->assertSame($first, $second);
        $this->assertNotNull($second);
    }
}
