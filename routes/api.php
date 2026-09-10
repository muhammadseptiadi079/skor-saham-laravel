<?php

use App\Http\Controllers\AnalyzeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/analyze', [AnalyzeController::class, 'analyze']);
