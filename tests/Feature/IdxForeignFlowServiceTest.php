<?php

namespace Tests\Feature;

use App\Services\IdxForeignFlowService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdxForeignFlowServiceTest extends TestCase
{
    public function test_parses_foreign_buy_sell_from_primary_field_names(): void
    {
        Http::fake([
            'idx.co.id/*' => Http::response([
                'data' => [
                    ['ForeignBuyValue' => 5_000_000, 'ForeignSellValue' => 1_000_000, 'Date' => '2024-05-01'],
                ],
            ]),
        ]);

        $result = app(IdxForeignFlowService::class)->latestFlowFor('BBCA');

        $this->assertSame(5_000_000.0, $result['buyValue']);
        $this->assertSame(1_000_000.0, $result['sellValue']);
        $this->assertSame('2024-05-01', $result['asOfDate']);
    }

    public function test_falls_back_to_alternate_field_names(): void
    {
        Http::fake([
            'idx.co.id/*' => Http::response([
                'Results' => [
                    ['NilaiBeliAsing' => '2500000', 'NilaiJualAsing' => '500000', 'Tanggal' => '2024-05-02'],
                ],
            ]),
        ]);

        $result = app(IdxForeignFlowService::class)->latestFlowFor('BBRI');

        $this->assertSame(2_500_000.0, $result['buyValue']);
        $this->assertSame(500_000.0, $result['sellValue']);
    }

    public function test_returns_null_when_endpoint_fails(): void
    {
        Http::fake([
            'idx.co.id/*' => Http::response('', 500),
        ]);

        $result = app(IdxForeignFlowService::class)->latestFlowFor('BBCA');

        $this->assertNull($result);
    }

    public function test_returns_null_when_response_shape_is_unrecognized(): void
    {
        Http::fake([
            'idx.co.id/*' => Http::response(['unexpected' => 'shape']),
        ]);

        $result = app(IdxForeignFlowService::class)->latestFlowFor('BBCA');

        $this->assertNull($result);
    }

    public function test_returns_null_when_buy_sell_fields_missing(): void
    {
        Http::fake([
            'idx.co.id/*' => Http::response(['data' => [['SomeOtherField' => 1]]]),
        ]);

        $result = app(IdxForeignFlowService::class)->latestFlowFor('BBCA');

        $this->assertNull($result);
    }

    public function test_strips_jk_suffix_from_ticker(): void
    {
        Http::fake(['idx.co.id/*' => Http::response(['data' => []])]);

        app(IdxForeignFlowService::class)->latestFlowFor('BBCA.JK');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'kodeEmiten=BBCA')
            && ! str_contains($request->url(), 'BBCA.JK'));
    }
}
