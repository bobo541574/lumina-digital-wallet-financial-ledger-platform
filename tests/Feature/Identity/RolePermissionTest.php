<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('seeds the four system roles and permission catalog', function () {
    foreach (Role::cases() as $role) {
        expect(App\Modules\Identity\Models\Role::where('name', $role->value)->exists())->toBeTrue();
    }

    expect(App\Modules\Identity\Models\Permission::count())->toBe(count(Permission::cases()));
});

it('resolves permissions through an assigned role', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Customer);

    expect($user->hasRole(Role::Customer))->toBeTrue()
        ->and($user->hasPermissionTo(Permission::TransfersCreate))->toBeTrue()
        ->and($user->hasPermissionTo(Permission::DepositsApprove))->toBeFalse();
});

it('supports direct permissions in addition to roles', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Customer);

    expect($user->hasPermissionTo(Permission::LedgerView))->toBeFalse();

    $user->givePermissionTo(Permission::LedgerView);

    expect($user->hasPermissionTo(Permission::LedgerView))->toBeTrue()
        ->and($user->getPermissionNames())->toContain('ledger.view');
});

it('exposes permissions as gate abilities', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::FinanceOperator);

    expect(Gate::forUser($user)->allows(Permission::DepositsApprove->value))->toBeTrue()
        ->and(Gate::forUser($user)->allows(Permission::TransfersCreate->value))->toBeFalse();
});

it('revokes roles and permissions', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Auditor);
    $user->givePermissionTo(Permission::DepositsApprove);

    $user->removeRole(Role::Auditor);
    $user->revokePermissionTo(Permission::DepositsApprove);

    expect($user->hasRole(Role::Auditor))->toBeFalse()
        ->and($user->hasPermissionTo(Permission::AuditView))->toBeFalse()
        ->and($user->hasPermissionTo(Permission::DepositsApprove))->toBeFalse();
});

it('assigns roles to newly registered users', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Carol',
        'email' => 'carol@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $user = User::where('email', 'carol@example.com')->first();

    expect($user->hasRole(Role::Customer))->toBeTrue()
        ->and($user->id)->toMatch('/^[0-9a-f-]{36}$/');
});
