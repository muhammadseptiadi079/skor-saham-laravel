<?php

use App\Http\Controllers\AccuracyController;
use App\Http\Controllers\AnalyzeController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\IpoController;
use App\Http\Controllers\ManualNewsController;
use App\Http\Controllers\ScreenerController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/analyze', [AnalyzeController::class, 'analyze']);

Route::get('/watchlist', [WatchlistController::class, 'index']);
Route::post('/watchlist', [WatchlistController::class, 'store']);
Route::post('/watchlist/starter-pack', [WatchlistController::class, 'starterPack']);
Route::patch('/watchlist/{watchlistItem}', [WatchlistController::class, 'update']);
Route::delete('/watchlist/{watchlistItem}', [WatchlistController::class, 'destroy']);

Route::get('/history', [HistoryController::class, 'index']);

Route::get('/screener', [ScreenerController::class, 'index']);

Route::get('/ipo', [IpoController::class, 'index']);

Route::get('/accuracy', [AccuracyController::class, 'index']);

Route::get('/news/manual', [ManualNewsController::class, 'index']);
Route::post('/news/manual', [ManualNewsController::class, 'store']);
Route::delete('/news/manual/{manualNewsItem}', [ManualNewsController::class, 'destroy']);
