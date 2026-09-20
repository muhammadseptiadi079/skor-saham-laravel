<?php

namespace Tests\Feature;

use App\Services\IdxCorporateActionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdxCorporateActionServiceTest extends TestCase
{
    public function test_detects_upcoming_stock_split(): void
    {
        $soon = date('Y-m-d', strtotime('+5 days'));

        Http::fake([
            'idx.co.id/*' => Http::response([
                'data' => [
                    ['Code' => 'BBCA', 'Subject' => 'Stock Split 1:5', 'ExDate' => $soon],
                ],
            ]),
        ]);

        $result = app(IdxCorporateActionService::class)->upcomingActionFor('BBCA');

        $this->assertSame('split', $result['type']);
        $this->assertSame($soon, $result['date']);
    }

    public function test_detects_upcoming_rights_issue_via_hmetd_keyword(): void
    {
        $soon = date('Y-m-d', strtotime('+7 days'));

        Http::fake([
            'idx.co.id/*' => Http::response([
                'data' => [
                    ['KodeEmiten' => 'BBRI', 'Keterangan' => 'Pelaksanaan HMETD', 'RecordDate' => $soon],
                ],
            ]),
        ]);

        $result = app(IdxCorporateActionService::class)->upcomingActionFor('BBRI');

        $this->assertSame('rights_issue', $result['type']);
    }

    public function test_ignores_actions_for_other_tickers(): void
    {
        $soon = date('Y-m-d', strtotime('+5 days'));

        Http::fake([
            'idx.co.id/*' => Http::response([
                'data' => [
                    ['Code' => 'OTHR', 'Subject' => 'Stock Split', 'ExDate' => $soon],
                ],
            ]),
        ]);

        $result = app(IdxCorporateActionService::class)->upcomingActionFor('BBCA');

        $this->assertNull($result);
    }

    public function test_ignores_past_actions(): void
    {
        $past = date('Y-m-d', strtotime('-5 days'));

        Http::fake([
            'idx.co.id/*' => Http::response([
                'data' => [
                    ['Code' => 'BBCA', 'Subject' => 'Stock Split', 'ExDate' => $past],
                ],
            ]),
        ]);

        $result = app(IdxCorporateActionService::class)->upcomingActionFor('BBCA');

        $this->assertNull($result);
    }

    public function test_ignores_unrelated_corporate_actions_like_dividends(): void
    {
        $soon = date('Y-m-d', strtotime('+5 days'));

        Http::fake([
            'idx.co.id/*' => Http::response([
                'data' => [
                    ['Code' => 'BBCA', 'Subject' => 'Cash Dividend', 'ExDate' => $soon],
                ],
            ]),
        ]);

        $result = app(IdxCorporateActionService::class)->upcomingActionFor('BBCA');

        $this->assertNull($result);
    }

    public function test_picks_the_nearest_action_when_multiple_match(): void
    {
        $near = date('Y-m-d', strtotime('+3 days'));
        $far = date('Y-m-d', strtotime('+20 days'));

        Http::fake([
            'idx.co.id/*' => Http::response([
                'data' => [
                    ['Code' => 'BBCA', 'Subject' => 'Stock Split', 'ExDate' => $far],
                    ['Code' => 'BBCA', 'Subject' => 'Right Issue', 'ExDate' => $near],
                ],
            ]),
        ]);

        $result = app(IdxCorporateActionService::class)->upcomingActionFor('BBCA');

        $this->assertSame($near, $result['date']);
        $this->assertSame('rights_issue', $result['type']);
    }

    public function test_returns_null_when_endpoint_fails(): void
    {
        Http::fake(['idx.co.id/*' => Http::response('', 500)]);

        $result = app(IdxCorporateActionService::class)->upcomingActionFor('BBCA');

        $this->assertNull($result);
    }
}
