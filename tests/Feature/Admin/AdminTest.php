<?php

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Wallets\Enums\WalletStatus;
use Laravel\Sanctum\Sanctum;
use Tests\Support\WalletTestSupport;

uses(WalletTestSupport::class);

beforeEach(function () {
    $this->seedFinancials();
    $this->seed(RolePermissionSeeder::class);

    $this->customer = User::factory()->create();
    $this->customer->assignRole(Role::Customer->value);
    $this->wallet = $this->openWallet($this->customer, 'USD');
    $this->fundWallet($this->wallet, 10000);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::Admin->value);

    $this->finance = User::factory()->create();
    $this->finance->assignRole(Role::FinanceOperator->value);

    $this->auditor = User::factory()->create();
    $this->auditor->assignRole(Role::Auditor->value);
});

it('forbids customers from admin endpoints', function () {
    Sanctum::actingAs($this->customer);

    $this->getJson('/api/v1/admin/wallets')->assertStatus(403);
    $this->postJson("/api/v1/admin/wallets/{$this->wallet->id}/freeze")->assertStatus(403);
    $this->getJson('/api/v1/admin/journal-entries')->assertStatus(403);
    $this->getJson('/api/v1/admin/audit-events')->assertStatus(403);
});

it('lets an admin freeze and unfreeze a wallet and records an audit event', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/wallets/{$this->wallet->id}/freeze")
        ->assertOk()
        ->assertJsonPath('data.status', 'frozen');

    expect($this->wallet->fresh()->status)->toBe(WalletStatus::Frozen)
        ->and(AuditEvent::where('action', 'wallet.freeze')->where('subject_id', $this->wallet->id)->exists())->toBeTrue();

    $this->postJson("/api/v1/admin/wallets/{$this->wallet->id}/unfreeze")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect($this->wallet->fresh()->status)->toBe(WalletStatus::Active);
});

it('lets finance search journal entries', function () {
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/admin/journal-entries?entry_type=deposit')
        ->assertOk()
        ->assertJsonPath('data.0.entry_type', 'deposit');
});

it('lets an auditor read the audit trail but not mutate wallets', function () {
    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/wallets/{$this->wallet->id}/freeze")->assertOk();

    Sanctum::actingAs($this->auditor);

    $this->getJson('/api/v1/admin/audit-events?action=wallet.freeze')
        ->assertOk()
        ->assertJsonPath('data.0.action', 'wallet.freeze');

    $this->postJson("/api/v1/admin/wallets/{$this->wallet->id}/unfreeze")->assertStatus(403);
});

it('exposes provider transaction lookup to finance', function () {
    Sanctum::actingAs($this->customer);
    $intent = $this->withHeaders(['Idempotency-Key' => 'admin-prov-1'])
        ->postJson('/api/v1/deposits/provider', [
            'wallet_id' => $this->wallet->id,
            'amount' => 2500,
            'provider' => 'fake',
        ])->json('data');

    Sanctum::actingAs($this->finance);
    $this->getJson('/api/v1/admin/provider-transactions?provider=fake&reference='.$intent['provider_reference'])
        ->assertOk()
        ->assertJsonPath('data.0.type', 'deposit')
        ->assertJsonPath('data.0.provider_reference', $intent['provider_reference']);
});
