<?php

use App\Modules\Transfers\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('transfers', [TransferController::class, 'store']);
    Route::get('transfers/{transfer}', [TransferController::class, 'show']);
});
