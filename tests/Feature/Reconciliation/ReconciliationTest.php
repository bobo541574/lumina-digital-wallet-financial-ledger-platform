<?php

use App\Modules\Deposits\Enums\DepositChannel;
use App\Modules\Deposits\Enums\DepositStatus;
use App\Modules\Deposits\Models\Deposit;
use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Reconciliation\Enums\MismatchClassification;
use App\Modules\Reconciliation\Enums\ReconciliationItemStatus;
use App\Modules\Reconciliation\Enums\ReconciliationLayer;
use App\Modules\Reconciliation\Enums\ReconciliationRunStatus;
use App\Modules\Reconciliation\Models\ProviderSettlement;
use App\Modules\Reconciliation\Models\ProviderSettlementLine;
use App\Modules\Reconciliation\Models\ReconciliationItem;
use App\Modules\Reconciliation\Services\ReconciliationRunner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\WalletTestSupport;

uses(WalletTestSupport::class);

beforeEach(function () {
    $this->seedFinancials();
    $this->seed(RolePermissionSeeder::class);

    $this->customer = User::factory()->create();
    $this->customer->assignRole(Role::Customer->value);
    $this->wallet = $this->openWallet($this->customer, 'USD');
    $this->fundWallet($this->wallet, 100000);

    $this->finance = User::factory()->create();
    $this->finance->assignRole(Role::FinanceOperator->value);
});

function succeededProviderDeposit(User $customer, string $walletId, string $transactionId, int $amount): Deposit
{
    return Deposit::create([
        'reference' => 'DEP-'.Str::upper(Str::random(8)),
        'user_id' => $customer->id,
        'wallet_id' => $walletId,
        'currency_code' => 'USD',
        'amount' => $amount,
        'channel' => DepositChannel::Provider,
        'status' => DepositStatus::Succeeded,
        'provider' => 'kbzpay',
        'provider_transaction_id' => $transactionId,
        'occurred_at' => now(),
    ]);
}

function settlementWithLine(string $transactionId, int $lineAmount, ?int $netAmount = null): ProviderSettlement
{
    $settlement = ProviderSettlement::create([
        'provider' => 'kbzpay',
        'settlement_reference' => 'SET-'.Str::upper(Str::random(8)),
        'currency_code' => 'USD',
        'gross_amount' => $lineAmount,
        'net_amount' => $netAmount ?? $lineAmount,
        'statement_date' => now()->toDateString(),
        'status' => 'received',
    ]);

    ProviderSettlementLine::create([
        'settlement_id' => $settlement->id,
        'type' => 'deposit',
        'provider_transaction_id' => $transactionId,
        'currency_code' => 'USD',
        'gross_amount' => $lineAmount,
        'net_amount' => $netAmount ?? $lineAmount,
        'occurred_at' => now(),
    ]);

    return $settlement;
}

it('reports a clean wallet when the projection matches the ledger', function () {
    $run = app(ReconciliationRunner::class)->run(ReconciliationLayer::Wallet);

    expect($run->status)->toBe(ReconciliationRunStatus::Completed)
        ->and($run->discrepancies_count)->toBe(0)
        ->and($run->items_count)->toBe(0);
});

it('detects wallet balance drift as an amount mismatch', function () {
    DB::table('wallet_balances')
        ->where('wallet_id', $this->wallet->id)
        ->update(['posted_balance' => DB::raw('posted_balance + 1')]);

    $run = app(ReconciliationRunner::class)->run(ReconciliationLayer::Wallet);

    expect($run->status)->toBe(ReconciliationRunStatus::CompletedWithDiscrepancies)
        ->and($run->discrepancies_count)->toBe(1);

    $item = ReconciliationItem::query()->sole();

    expect($item->classification)->toBe(MismatchClassification::AmountMismatch)
        ->and($item->status)->toBe(ReconciliationItemStatus::Open)
        ->and($item->layer)->toBe(ReconciliationLayer::Wallet)
        ->and($item->subject_id)->toBe($this->wallet->id)
        ->and($item->difference)->toBe(-1);
});

it('matches a settlement line to a succeeded provider deposit', function () {
    succeededProviderDeposit($this->customer, $this->wallet->id, 'TXN-MATCH', 5000);
    $settlement = settlementWithLine('TXN-MATCH', 5000);

    $provider = app(ReconciliationRunner::class)->run(ReconciliationLayer::Provider);
    $bank = app(ReconciliationRunner::class)->run(ReconciliationLayer::Settlement);

    expect($provider->discrepancies_count)->toBe(0)
        ->and($bank->discrepancies_count)->toBe(0)
        ->and($settlement->fresh()->status)->toBe('matched')
        ->and($settlement->lines()->sole()->matched)->toBeTrue();
});

it('flags a settlement amount mismatch', function () {
    succeededProviderDeposit($this->customer, $this->wallet->id, 'TXN-DIFF', 5000);
    settlementWithLine('TXN-DIFF', 4000);

    $run = app(ReconciliationRunner::class)->run(ReconciliationLayer::Provider);

    expect($run->discrepancies_count)->toBe(1);

    $item = ReconciliationItem::query()->where('classification', MismatchClassification::AmountMismatch)->sole();

    expect($item->difference)->toBe(1000)
        ->and($item->provider)->toBe('kbzpay');
});

it('flags a movement missing from the settlement', function () {
    succeededProviderDeposit($this->customer, $this->wallet->id, 'TXN-ABSENT', 7000);
    settlementWithLine('TXN-OTHER', 7000);

    $run = app(ReconciliationRunner::class)->run(ReconciliationLayer::Provider);

    expect(ReconciliationItem::query()->where('classification', MismatchClassification::Missing)->count())->toBe(2)
        ->and($run->discrepancies_count)->toBe(2);
});

it('resolves a discrepancy once and rejects a second resolution', function () {
    DB::table('wallet_balances')
        ->where('wallet_id', $this->wallet->id)
        ->update(['posted_balance' => DB::raw('posted_balance + 5')]);

    app(ReconciliationRunner::class)->run(ReconciliationLayer::Wallet);
    $item = ReconciliationItem::query()->sole();

    Sanctum::actingAs($this->finance);

    $this->postJson("/api/v1/admin/reconciliation/items/{$item->id}/resolve", [
        'status' => 'resolved',
        'note' => 'Manual adjustment approved by finance.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolved_by', $this->finance->id);

    $this->postJson("/api/v1/admin/reconciliation/items/{$item->id}/resolve", [
        'status' => 'resolved',
    ])->assertStatus(409)
        ->assertJsonPath('error.code', 'RECONCILIATION_ITEM_ALREADY_RESOLVED');
});

it('runs and alerts through the console commands', function () {
    DB::table('wallet_balances')
        ->where('wallet_id', $this->wallet->id)
        ->update(['posted_balance' => DB::raw('posted_balance + 1')]);

    $this->artisan('reconciliation:run', ['--layer' => 'wallet'])
        ->expectsOutputToContain('wallet: completed_with_discrepancies')
        ->assertExitCode(0);

    $this->artisan('reconciliation:alert')
        ->expectsOutputToContain('unresolved reconciliation discrepancy')
        ->assertExitCode(0);
});

it('gates reconciliation endpoints on reconciliation permissions', function () {
    Sanctum::actingAs($this->customer);

    $this->getJson('/api/v1/admin/reconciliation/runs')->assertStatus(403);
    $this->getJson('/api/v1/admin/reconciliation/items')->assertStatus(403);
    $this->postJson('/api/v1/admin/reconciliation/run', ['layer' => 'wallet'])->assertStatus(403);

    Sanctum::actingAs($this->finance);

    $this->postJson('/api/v1/admin/reconciliation/run', ['layer' => 'wallet'])
        ->assertCreated()
        ->assertJsonPath('data.layer', 'wallet')
        ->assertJsonPath('data.status', 'completed');

    $this->getJson('/api/v1/admin/reconciliation/runs')
        ->assertOk()
        ->assertJsonPath('data.0.layer', 'wallet');
});
