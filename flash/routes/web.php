<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SPA Catch-All
|--------------------------------------------------------------------------
| Serve the Vue SPA for all non-API routes.
| Static assets (JS, CSS, images) in public/ are served directly by the web server.
*/

Route::get('/{any?}', function () {
    return view('spa');
})->where('any', '^(?!api).*$')->name('spa');
