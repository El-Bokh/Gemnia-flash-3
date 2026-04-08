<?php

use App\Http\Controllers\Api\GoogleAuthController;
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

Route::get('/{any?}', function () {
    return view('spa');
})->where('any', '^(?!api|auth/google).*$')->name('spa');
