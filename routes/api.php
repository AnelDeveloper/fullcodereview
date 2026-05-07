<?php

use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CreditsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GithubController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\LemonSqueezyController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('catalog', [CatalogController::class, 'index']);
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::get('github/login', [GithubController::class, 'login']);
Route::get('github/callback', [GithubController::class, 'callback']);

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

// Lemon Squeezy webhook — signed by the LS-side secret, no auth required
Route::post('lemon-squeezy/webhook', [LemonSqueezyController::class, 'webhook']);

// Email verification — link from the email is signed, no auth required
Route::get('auth/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// Resend — public + throttled. Users hitting the post-register check-email
// page have no auth token yet, so this can't live behind auth.api.
Route::post('auth/email/resend', [AuthController::class, 'resendVerification'])
    ->middleware('throttle:6,1');

// Authenticated
Route::middleware('auth.api')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::get('me/credits', [CreditsController::class, 'index']);
    Route::get('me/dashboard', [DashboardController::class, 'index']);

    Route::post('lemon-squeezy/checkout', [LemonSqueezyController::class, 'checkout']);
    Route::post('lemon-squeezy/sync', [LemonSqueezyController::class, 'sync']);

    Route::get('github/repos', [GithubController::class, 'repos']);

    Route::post('analyses/run', [AnalysisController::class, 'run']);
    Route::get('analyses/history', [AnalysisController::class, 'history']);
    Route::get('analyses/{id}', [AnalysisController::class, 'show'])->whereNumber('id');
    Route::get('analyses/{id}/report.pdf', [AnalysisController::class, 'reportPdf'])->whereNumber('id');
});
