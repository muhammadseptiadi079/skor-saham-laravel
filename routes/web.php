<?php

use Illuminate\Support\Facades\Route;

// The PWA shell lives at public/pwa-shell.html (not public/index.html) so it never collides
// with Laravel's own front controller — "/" always goes through the router, and this is where
// it lands.
Route::get('/', function () {
    return response()->file(public_path('pwa-shell.html'), ['Content-Type' => 'text/html']);
});
