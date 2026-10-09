<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\LedgerAccount;
use Laravel\Sanctum\Sanctum;
use Tests\Support\WalletTestSupport;

uses(WalletTestSupport::class);

beforeEach(function () {
    $this->seedFinancials();
    $this->seed(RolePermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Customer->value);

    Sanctum::actingAs($this->user);
});

it('opens a zero-balance wallet with a ledger account', function () {
    $response = $this->postJson('/api/v1/wallets', ['currency' => 'USD', 'name' => 'Main']);

    $response->assertCreated()
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.balance.posted_balance', 0)
        ->assertJsonPath('data.balance.available_balance', 0);

    $walletId = $response->json('data.id');

    expect(LedgerAccount::where('wallet_id', $walletId)->exists())->toBeTrue();
});

it('reuses the same wallet when opened twice in one currency', function () {
    $first = $this->postJson('/api/v1/wallets', ['currency' => 'USD'])->json('data.id');
    $second = $this->postJson('/api/v1/wallets', ['currency' => 'USD'])->json('data.id');

    expect($second)->toBe($first);
});

it('lists only the caller wallets', function () {
    $this->openWallet($this->user, 'USD');
    $this->openWallet($this->user, 'MMK');

    $other = User::factory()->create();
    $this->openWallet($other, 'USD');

    $this->getJson('/api/v1/wallets')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('reports the available balance', function () {
    $wallet = $this->openWallet($this->user, 'USD');
    $this->fundWallet($wallet, 4200);

    $this->getJson("/api/v1/wallets/{$wallet->id}/balance")
        ->assertOk()
        ->assertJsonPath('data.posted_balance', 4200)
        ->assertJsonPath('data.reserved_balance', 0)
        ->assertJsonPath('data.available_balance', 4200)
        ->assertJsonPath('data.minor_units', 2);
});

it('forbids reading another user wallet balance', function () {
    $other = User::factory()->create();
    $other->assignRole(Role::Customer->value);
    $wallet = $this->openWallet($other, 'USD');

    $this->getJson("/api/v1/wallets/{$wallet->id}/balance")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});
