<?php

use App\Modules\Admin\Http\Controllers\AdminController;
use App\Modules\Audit\Http\Controllers\AuditEventController;
use App\Modules\Identity\Http\Middleware\RequireTwoFactor;
use App\Modules\Ledger\Http\Controllers\AdminJournalEntryController;
use App\Modules\Reconciliation\Http\Controllers\AdminReconciliationController;
use App\Modules\Wallets\Http\Controllers\AdminWalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:admin', RequireTwoFactor::class])->group(function () {
    Route::get('wallets', [AdminWalletController::class, 'index']);
    Route::get('wallets/{wallet}', [AdminWalletController::class, 'show']);
    Route::post('wallets/{wallet}/freeze', [AdminWalletController::class, 'freeze']);
    Route::post('wallets/{wallet}/unfreeze', [AdminWalletController::class, 'unfreeze']);

    Route::get('users', [AdminController::class, 'users']);
    Route::get('deposits', [AdminController::class, 'deposits']);
    Route::get('withdrawals', [AdminController::class, 'withdrawals']);

    Route::get('transfers', [AdminController::class, 'transfers']);
    Route::get('journal-entries', [AdminController::class, 'journalEntries']);
    Route::get('provider-transactions', [AdminController::class, 'providerTransactions']);

    Route::get('reconciliation/runs', [AdminReconciliationController::class, 'runs']);
    Route::get('reconciliation/runs/{run}', [AdminReconciliationController::class, 'show']);
    Route::get('reconciliation/items', [AdminReconciliationController::class, 'items']);
    Route::post('reconciliation/run', [AdminReconciliationController::class, 'run']);
    Route::post('reconciliation/items/{item}/resolve', [AdminReconciliationController::class, 'resolve']);

    Route::post('journal-entries/{journalEntry}/reverse', [AdminJournalEntryController::class, 'reverse']);

    Route::get('audit-events', [AuditEventController::class, 'index']);
});
