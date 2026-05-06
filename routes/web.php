<?php

use Illuminate\Support\Facades\Route;

// Explicit health endpoint — Laravel 11 also registers /up via
// `withRouting(health: '/up')` in bootstrap/app.php, but defining it here
// guards against route-ordering issues with the catch-all below.
Route::get('/up', fn () => response('OK', 200));

// SPA catch-all — anything that isn't /api/* or /up returns the Vue shell.
// The negative lookahead keeps /up from being swallowed.
Route::get('{any?}', fn () => view('application'))
    ->where('any', '^(?!up$|api(/|$)).*');
