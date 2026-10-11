<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
|
| The product is a single-page application. Every non-API, non-health route
| returns the Blade shell that boots the Vue app; client-side routing takes
| over from there. API and framework routes are excluded via the pattern.
|
*/

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|up|storage|sanctum).*$')->name('spa');
