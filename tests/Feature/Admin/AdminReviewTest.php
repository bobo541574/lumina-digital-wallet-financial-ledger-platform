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

    $this->customer = User::factory()->create();
    $this->customer->assignRole(Role::Customer->value);
    $this->wallet = $this->openWallet($this->customer, 'USD');
    $this->fundWallet($this->wallet, 100000);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::Admin->value);

    $this->finance = User::factory()->create();
    $this->finance->assignRole(Role::FinanceOperator->value);
});

it('forbids customers from admin review endpoints', function () {
    Sanctum::actingAs($this->customer);

    $this->getJson('/api/v1/admin/deposits')->assertStatus(403);
    $this->getJson('/api/v1/admin/withdrawals')->assertStatus(403);
    $this->getJson('/api/v1/admin/users')->assertStatus(403);
});

it('lets finance review deposits and withdrawals with status filters', function () {
    Sanctum::actingAs($this->customer);

    $this->withHeaders(['Idempotency-Key' => 'review-deposit-1'])
        ->postJson('/api/v1/deposits', ['wallet_id' => $this->wallet->id, 'amount' => 5000])
        ->assertCreated();

    $this->withHeaders(['Idempotency-Key' => 'review-withdrawal-1'])
        ->postJson('/api/v1/withdrawals', [
            'wallet_id' => $this->wallet->id,
            'amount' => 2000,
            'provider' => 'fake',
        ])->assertCreated();

    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/admin/deposits')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'awaiting_approval')
        ->assertJsonPath('data.0.amount', 5000);

    $this->getJson('/api/v1/admin/deposits?status=pending')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/v1/admin/withdrawals?status=reserved')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'reserved')
        ->assertJsonPath('data.0.provider', 'fake');
});

it('lets an admin list and search customers by role', function () {
    $target = User::factory()->create(['name' => 'Daw Mya', 'email' => 'mya@example.com']);
    $target->assignRole(Role::Customer->value);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/users?role=customer&q=mya')
        ->assertOk()
        ->assertJsonPath('data.0.email', 'mya@example.com')
        ->assertJsonPath('data.0.roles.0', 'customer');
});

it('forbids finance from managing customers', function () {
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/admin/users')->assertStatus(403);
});
