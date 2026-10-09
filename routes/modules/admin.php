<?php

use App\Modules\Admin\Http\Controllers\AdminController;
use App\Modules\Audit\Http\Controllers\AuditEventController;
use App\Modules\Wallets\Http\Controllers\AdminWalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('wallets', [AdminWalletController::class, 'index']);
    Route::get('wallets/{wallet}', [AdminWalletController::class, 'show']);
    Route::post('wallets/{wallet}/freeze', [AdminWalletController::class, 'freeze']);
    Route::post('wallets/{wallet}/unfreeze', [AdminWalletController::class, 'unfreeze']);

    Route::get('transfers', [AdminController::class, 'transfers']);
    Route::get('journal-entries', [AdminController::class, 'journalEntries']);
    Route::get('provider-transactions', [AdminController::class, 'providerTransactions']);

    Route::get('audit-events', [AuditEventController::class, 'index']);
});
