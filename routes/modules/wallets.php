<?php

use App\Modules\Wallets\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('wallets', [WalletController::class, 'index']);
    Route::post('wallets', [WalletController::class, 'store'])->middleware('throttle:money');
    Route::get('wallets/{wallet}/balance', [WalletController::class, 'balance']);
    Route::get('wallets/{wallet}/transactions', [WalletController::class, 'transactions']);
    Route::get('wallets/{wallet}/statement', [WalletController::class, 'statement']);
    Route::get('wallets/{wallet}/statement/export', [WalletController::class, 'exportStatement']);
});
