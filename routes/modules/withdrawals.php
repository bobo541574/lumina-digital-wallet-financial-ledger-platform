<?php

use App\Modules\Withdrawals\Http\Controllers\PayoutWebhookController;
use App\Modules\Withdrawals\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('withdrawals', [WithdrawalController::class, 'index']);
    Route::post('withdrawals', [WithdrawalController::class, 'store'])->middleware('throttle:money');
    Route::get('withdrawals/{withdrawal}', [WithdrawalController::class, 'show']);
    Route::post('withdrawals/{withdrawal}/initiate', [WithdrawalController::class, 'initiate']);
    Route::post('withdrawals/{withdrawal}/cancel', [WithdrawalController::class, 'cancel']);
});

// Payout callbacks authenticate via signature, not the session.
Route::post('payment-providers/{provider}/payout-webhook', [PayoutWebhookController::class, 'handle'])
    ->middleware('throttle:webhooks');
