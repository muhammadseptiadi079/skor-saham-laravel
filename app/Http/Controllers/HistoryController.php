<?php

namespace App\Http\Controllers;

use App\Models\AnalysisHistory;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = AnalysisHistory::query()->orderByDesc('generated_at');

        if ($ticker = $request->query('ticker')) {
            $query->where('ticker', strtoupper($ticker));
        }
        if ($market = $request->query('market')) {
            $query->where('market', $market);
        }

        return response()->json($query->limit(50)->get());
    }
}
