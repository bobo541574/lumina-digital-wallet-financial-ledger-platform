<?php

use App\Modules\Deposits\Http\Controllers\DepositController;
use App\Modules\PaymentProviders\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('deposits', [DepositController::class, 'index']);
    Route::post('deposits', [DepositController::class, 'store']);
    Route::post('deposits/provider', [DepositController::class, 'storeProvider']);
    Route::get('deposits/{deposit}', [DepositController::class, 'show']);
    Route::post('deposits/{deposit}/approve', [DepositController::class, 'approve']);
    Route::post('deposits/{deposit}/reject', [DepositController::class, 'reject']);
    Route::post('deposits/{deposit}/confirm', [DepositController::class, 'confirm']);
});

// Provider callbacks authenticate via signature, not the session.
Route::post('payment-providers/{provider}/webhook', [WebhookController::class, 'handle'])
    ->name('payment-providers.webhook');
