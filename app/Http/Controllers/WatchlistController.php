<?php

namespace App\Http\Controllers;

use App\Models\WatchlistItem;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function index()
    {
        return response()->json(
            WatchlistItem::orderByDesc('created_at')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ticker' => ['required', 'string', 'max:20'],
            'market' => ['required', 'in:idx,global'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        $validated['ticker'] = strtoupper($validated['ticker']);

        $item = WatchlistItem::firstOrCreate(
            ['ticker' => $validated['ticker'], 'market' => $validated['market']],
            ['name' => $validated['name'] ?? null],
        );

        return response()->json($item, 201);
    }

    public function destroy(WatchlistItem $watchlistItem)
    {
        $watchlistItem->delete();

        return response()->json(['deleted' => true]);
    }
}
