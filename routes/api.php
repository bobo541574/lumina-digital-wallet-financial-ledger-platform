<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (v1)
|--------------------------------------------------------------------------
|
| Each domain module owns a route file under routes/modules. They are loaded
| here inside the v1 prefix so the module boundary stays explicit.
|
*/

Route::prefix('v1')->group(function () {
    foreach (glob(base_path('routes/modules/*.php')) as $file) {
        require $file;
    }
});
