<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\WalletTestSupport;

uses(WalletTestSupport::class);

beforeEach(function () {
    $this->seedFinancials();
    $this->seed(RolePermissionSeeder::class);

    $this->alice = User::factory()->create();
    $this->alice->assignRole(Role::Customer->value);
    $this->wallet = $this->openWallet($this->alice, 'USD');
    $this->fundWallet($this->wallet, 10000);

    $this->bob = User::factory()->create();
    $this->bob->assignRole(Role::Customer->value);
    $this->bobWallet = $this->openWallet($this->bob, 'USD');
});

it('returns a unified history across all movement types with signed amounts', function () {
    Sanctum::actingAs($this->alice);

    $this->withHeaders(['Idempotency-Key' => 'hist-transfer-1'])->postJson('/api/v1/transfers', [
        'sender_wallet_id' => $this->wallet->id,
        'recipient_wallet_id' => $this->bobWallet->id,
        'currency' => 'USD',
        'amount' => 3000,
    ])->assertCreated();

    $response = $this->getJson("/api/v1/wallets/{$this->wallet->id}/transactions")->assertOk();

    $items = $response->json('data');
    expect($items)->toHaveCount(2);

    expect($items[0]['entry_type'])->toBe('transfer')
        ->and($items[0]['signed_amount'])->toBe(-3000)
        ->and($items[0]['direction'])->toBe('debit');

    expect($items[1]['entry_type'])->toBe('deposit')
        ->and($items[1]['signed_amount'])->toBe(10000);
})->group('history');

it('paginates history with a stable cursor', function () {
    Sanctum::actingAs($this->alice);

    for ($i = 0; $i < 5; $i++) {
        $this->fundWallet($this->wallet, 100);
    }

    $first = $this->getJson("/api/v1/wallets/{$this->wallet->id}/transactions?per_page=2")->assertOk();
    expect($first->json('data'))->toHaveCount(2);

    $cursor = $first->json('meta.pagination.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->getJson("/api/v1/wallets/{$this->wallet->id}/transactions?per_page=2&cursor={$cursor}")->assertOk();
    $firstIds = collect($first->json('data'))->pluck('id');
    $secondIds = collect($second->json('data'))->pluck('id');

    expect($firstIds->intersect($secondIds))->toBeEmpty();
})->group('history');

it('computes statement opening and closing balances', function () {
    Sanctum::actingAs($this->alice);

    $statement = $this->getJson("/api/v1/wallets/{$this->wallet->id}/statement")->assertOk()->json('data');

    expect($statement['opening_balance'])->toBe(0)
        ->and($statement['closing_balance'])->toBe(10000)
        ->and($statement['currency'])->toBe('USD')
        ->and($statement['movements'])->toHaveCount(1);
})->group('history');

it('forbids viewing another customer wallet history', function () {
    Sanctum::actingAs($this->bob);

    $this->getJson("/api/v1/wallets/{$this->wallet->id}/transactions")->assertStatus(403);
    $this->getJson("/api/v1/wallets/{$this->wallet->id}/statement")->assertStatus(403);
})->group('history');
