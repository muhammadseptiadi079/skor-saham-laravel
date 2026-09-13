<?php

namespace App\Http\Controllers;

use App\Models\AnalysisHistory;
use App\Models\WatchlistItem;
use Illuminate\Http\Request;

// A real (if lightweight) portfolio view over favorited watchlist items that have shares_owned +
// avg_buy_price set — unrealized P&L against the latest cached price. "Latest" here means
// analysis_history's most recent row for that ticker (from `stocks:refresh-scores` or a manual
// /api/analyze call), not a live quote — see priceAsOf on each holding, and the README note on
// why this can lag by up to a day.
class PortfolioController extends Controller
{
    private const CURRENCY_BY_MARKET = ['idx' => 'IDR', 'global' => 'USD'];

    public function index(Request $request)
    {
        $query = WatchlistItem::where('is_favorite', true)
            ->whereNotNull('shares_owned')
            ->whereNotNull('avg_buy_price');
        if ($market = $request->query('market')) {
            $query->where('market', $market);
        }

        $holdings = $query->get()->map(fn (WatchlistItem $item) => $this->presentHolding($item));

        $summaries = $holdings->groupBy('market')->map(function ($rows, $market) {
            $withPrice = $rows->whereNotNull('currentValue');
            $totalCostBasis = $rows->sum('costBasis');
            $totalCurrentValue = $withPrice->isNotEmpty() ? $withPrice->sum('currentValue') : null;
            $costBasisWithPrice = $withPrice->sum('costBasis');

            return [
                'market' => $market,
                'currency' => self::CURRENCY_BY_MARKET[$market] ?? 'USD',
                'holdingsCount' => $rows->count(),
                // Total value/P&L below only cover holdings where a cached price is available —
                // this can be fewer than holdingsCount for a stock never analyzed yet (see
                // priceAsOf on each holding). totalCostBasis is always the full known amount
                // invested, independent of whether a price is cached.
                'pricedHoldingsCount' => $withPrice->count(),
                'totalCostBasis' => $totalCostBasis,
                'totalCurrentValue' => $totalCurrentValue,
                'totalUnrealizedPnl' => $totalCurrentValue !== null ? $totalCurrentValue - $costBasisWithPrice : null,
                'totalUnrealizedPnlPct' => ($totalCurrentValue !== null && $costBasisWithPrice > 0)
                    ? ($totalCurrentValue - $costBasisWithPrice) / $costBasisWithPrice
                    : null,
            ];
        })->values();

        return response()->json([
            'holdings' => $holdings->values(),
            'summaries' => $summaries,
        ]);
    }

    private function presentHolding(WatchlistItem $item): array
    {
        $latest = AnalysisHistory::where('ticker', $item->ticker)
            ->where('market', $item->market)
            ->whereNotNull('price_at_generation')
            ->orderByDesc('generated_at')
            ->first(['price_at_generation', 'generated_at']);

        $currentPrice = $latest?->price_at_generation;
        $costBasis = $item->shares_owned * $item->avg_buy_price;
        $currentValue = $currentPrice !== null ? $item->shares_owned * $currentPrice : null;
        $unrealizedPnl = $currentValue !== null ? $currentValue - $costBasis : null;
        $unrealizedPnlPct = ($currentPrice !== null && $item->avg_buy_price > 0)
            ? ($currentPrice - $item->avg_buy_price) / $item->avg_buy_price
            : null;

        return [
            'id' => $item->id,
            'ticker' => $item->ticker,
            'market' => $item->market,
            'name' => $item->name,
            'sector' => $item->sector,
            'sharesOwned' => $item->shares_owned,
            'avgBuyPrice' => $item->avg_buy_price,
            'currentPrice' => $currentPrice,
            'priceAsOf' => $latest?->generated_at?->toIso8601String(),
            'costBasis' => $costBasis,
            'currentValue' => $currentValue,
            'unrealizedPnl' => $unrealizedPnl,
            'unrealizedPnlPct' => $unrealizedPnlPct,
        ];
    }
}
