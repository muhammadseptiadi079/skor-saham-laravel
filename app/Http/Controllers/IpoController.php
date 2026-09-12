<?php

namespace App\Http\Controllers;

use App\Models\IpoListing;
use Illuminate\Http\Request;

class IpoController extends Controller
{
    public function index(Request $request)
    {
        $query = IpoListing::query()->orderByDesc('ipo_date');

        if ($market = $request->query('market')) {
            $query->where('market', $market);
        }

        return response()->json($query->limit(50)->get());
    }
}
