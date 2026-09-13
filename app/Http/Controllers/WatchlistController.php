<?php

namespace App\Http\Controllers;

use App\Models\WatchlistItem;
use App\Support\Sectors;
use App\Support\SectorStarterPacks;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WatchlistController extends Controller
{
    public function index()
    {
        return response()->json(
            WatchlistItem::orderByDesc('is_favorite')->orderBy('sector')->orderByDesc('created_at')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ticker' => ['required', 'string', 'max:20'],
            'market' => ['required', 'in:idx,global'],
            'name' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', Rule::in(Sectors::ALL)],
        ]);
        $validated['ticker'] = strtoupper($validated['ticker']);

        $item = WatchlistItem::firstOrCreate(
            ['ticker' => $validated['ticker'], 'market' => $validated['market']],
            [
                'name' => $validated['name'] ?? null,
                'sector' => $validated['sector'] ?? Sectors::DEFAULT,
                'is_favorite' => false,
            ],
        );

        return response()->json($item, 201);
    }

    // Lets the user reclassify a stock's sector, mark/unmark it as a favorite (meaning "I actually
    // bought this one"), or record how much they hold — shares_owned + avg_buy_price is what turns
    // a favorited item into a portfolio holding for PortfolioController.
    public function update(Request $request, WatchlistItem $watchlistItem)
    {
        $validated = $request->validate([
            'sector' => ['sometimes', 'string', Rule::in(Sectors::ALL)],
            'is_favorite' => ['sometimes', 'boolean'],
            'shares_owned' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'avg_buy_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $watchlistItem->update($validated);

        return response()->json($watchlistItem);
    }

    // Bulk-adds a curated list of well-known IDX tickers for a sector (see SectorStarterPacks) —
    // built because the real gap users hit isn't a data source lacking sector coverage, it's not
    // knowing which tickers exist in a sector they haven't already followed. Skips tickers already
    // in the watchlist rather than overwriting their existing sector, so it never clobbers a
    // user's own reclassification.
    public function starterPack(Request $request)
    {
        $validated = $request->validate([
            'sector' => ['required', 'string', Rule::in(SectorStarterPacks::sectors())],
        ]);

        $added = [];
        $skipped = [];
        foreach (SectorStarterPacks::forSector($validated['sector']) as $pick) {
            $item = WatchlistItem::firstOrCreate(
                ['ticker' => $pick['ticker'], 'market' => 'idx'],
                ['name' => $pick['name'], 'sector' => $validated['sector'], 'is_favorite' => false],
            );
            if ($item->wasRecentlyCreated) {
                $added[] = $item;
            } else {
                $skipped[] = $item->ticker;
            }
        }

        return response()->json(['added' => $added, 'skipped' => $skipped]);
    }

    public function destroy(WatchlistItem $watchlistItem)
    {
        $watchlistItem->delete();

        return response()->json(['deleted' => true]);
    }
}
