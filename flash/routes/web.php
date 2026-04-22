<?php

use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\GumroadWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SPA Catch-All
|--------------------------------------------------------------------------
| Serve the Vue SPA for all non-API routes.
| Static assets (JS, CSS, images) in public/ are served directly by the web server.
*/

// Google OAuth routes
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

// Gumroad Ping endpoint (public, CSRF-exempt — see bootstrap/app.php).
Route::post('/webhook/gumroad', [GumroadWebhookController::class, 'handle'])
    ->name('webhook.gumroad');

Route::get('/{any?}', function () {
    $response = view()->exists('spa')
        ? response()->view('spa')
        : response(
            'Frontend entry view is missing. Upload flash/resources/views/spa.blade.php and redeploy the frontend assets.',
            503,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );

    return $response
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
})->where('any', '^(?!api|auth/google|webhook/).*$')->name('spa');
