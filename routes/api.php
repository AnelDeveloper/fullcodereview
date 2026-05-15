<?php

use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CreditsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GithubController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\VerificationController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('catalog', [CatalogController::class, 'index']);
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

// GitHub OAuth callback is public on purpose — GitHub redirects the user
// back here after they authorize, and at that moment we don't yet have an
// auth cookie context (the user is identified via the cached `state`
// parameter we sent to GitHub). Keep public.
Route::get('github/callback', [GithubController::class, 'callback']);

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

// Stripe webhook — verified by Stripe-Signature header, no auth required
Route::post('stripe/webhook', [StripeController::class, 'webhook']);

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

    Route::put('me/profile', [AuthController::class, 'updateProfile']);
    Route::post('me/password', [AuthController::class, 'changePassword']);

    Route::get('me/credits', [CreditsController::class, 'index']);
    Route::get('me/dashboard', [DashboardController::class, 'index']);

    Route::post('stripe/checkout', [StripeController::class, 'checkout']);
    Route::post('stripe/sync', [StripeController::class, 'sync']);

    Route::get('github/login', [GithubController::class, 'login']);
    Route::get('github/repos', [GithubController::class, 'repos']);
    Route::post('github/disconnect', [GithubController::class, 'disconnect']);

    Route::post('analyses/run', [AnalysisController::class, 'run']);
    Route::get('analyses/history', [AnalysisController::class, 'history']);
    Route::get('analyses/{id}/status', [AnalysisController::class, 'status'])->whereNumber('id');
    Route::post('analyses/{id}/cancel', [AnalysisController::class, 'cancel'])->whereNumber('id');
    Route::get('analyses/{id}', [AnalysisController::class, 'show'])->whereNumber('id');
    Route::get('analyses/{id}/report.pdf', [AnalysisController::class, 'reportPdf'])->whereNumber('id');

    // Owner-only: send my analysis to the senior-engineer review queue
    Route::post('analyses/{id}/verification/submit-for-review', [VerificationController::class, 'submitForReview'])->whereNumber('id');

    // Reviewer-only: queue, approve, finalize, user management
    Route::middleware('reviewer')->group(function () {
        Route::get('reviewer/queue', [VerificationController::class, 'queue']);
        Route::post('analyses/{id}/verification/approve', [VerificationController::class, 'approve'])->whereNumber('id');
        Route::post('analyses/{id}/verification/finalize', [VerificationController::class, 'finalize'])->whereNumber('id');

        Route::get('admin/users', [AdminController::class, 'users']);
        Route::post('admin/users', [AdminController::class, 'store']);
        Route::patch('admin/users/{id}', [AdminController::class, 'update'])->whereNumber('id');
        Route::delete('admin/users/{id}', [AdminController::class, 'destroy'])->whereNumber('id');
        Route::post('admin/users/{id}/restore', [AdminController::class, 'restore'])->whereNumber('id');
        Route::post('admin/users/{id}/reviewer', [AdminController::class, 'setReviewer'])->whereNumber('id');
        Route::get('admin/users/{id}/credits', [AdminController::class, 'credits'])->whereNumber('id');
        Route::post('admin/users/{id}/credits', [AdminController::class, 'grantCredits'])->whereNumber('id');
    });
});
