<?php

namespace App\Http\Controllers;

use App\Models\WatchlistItem;
use App\Support\Sectors;
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

    // Lets the user reclassify a stock's sector, or mark/unmark it as a favorite (meaning "I
    // actually bought this one" — a lightweight way to tell it apart from stocks just being
    // watched, without a full portfolio/holdings feature).
    public function update(Request $request, WatchlistItem $watchlistItem)
    {
        $validated = $request->validate([
            'sector' => ['sometimes', 'string', Rule::in(Sectors::ALL)],
            'is_favorite' => ['sometimes', 'boolean'],
        ]);

        $watchlistItem->update($validated);

        return response()->json($watchlistItem);
    }

    public function destroy(WatchlistItem $watchlistItem)
    {
        $watchlistItem->delete();

        return response()->json(['deleted' => true]);
    }
}
