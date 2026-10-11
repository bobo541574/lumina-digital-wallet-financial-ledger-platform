<?php

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Role as RoleModel;
use App\Modules\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::Admin->value);

    $this->finance = User::factory()->create();
    $this->finance->assignRole(Role::FinanceOperator->value);

    $this->auditor = User::factory()->create();
    $this->auditor->assignRole(Role::Auditor->value);

    $this->customer = User::factory()->create();
    $this->customer->assignRole(Role::Customer->value);
});

it('forbids non-admins from access management', function () {
    $customerRoleId = RoleModel::where('name', Role::Customer->value)->value('id');

    foreach ([$this->customer, $this->finance, $this->auditor] as $user) {
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/roles')->assertStatus(403);
        $this->getJson('/api/v1/admin/permissions')->assertStatus(403);
        $this->getJson("/api/v1/admin/users/{$this->customer->id}")->assertStatus(403);
        $this->putJson("/api/v1/admin/users/{$this->customer->id}/roles", ['roles' => ['customer']])->assertStatus(403);
        $this->putJson("/api/v1/admin/roles/{$customerRoleId}/permissions", ['permissions' => []])->assertStatus(403);
    }
});

it('lists roles with their permissions and user counts', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/v1/admin/roles')->assertOk();

    $admin = collect($response->json('data'))->firstWhere('name', Role::Admin->value);

    expect($admin['permissions'])->toContain(Permission::RolesManage->value)
        ->and($admin['users_count'])->toBe(1)
        ->and($admin['is_system'])->toBeTrue();
});

it('lists the permission catalog', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/permissions')
        ->assertOk()
        ->assertJsonCount(count(Permission::cases()), 'data')
        ->assertJsonFragment(['name' => Permission::LedgerCorrect->value]);
});

it('updates a role permission set and records an audit event', function () {
    Sanctum::actingAs($this->admin);

    $role = RoleModel::where('name', Role::FinanceOperator->value)->firstOrFail();

    $this->putJson("/api/v1/admin/roles/{$role->id}/permissions", [
        'permissions' => [Permission::DepositsApprove->value, Permission::DepositsConfirm->value],
    ])->assertOk()->assertJsonPath('data.name', Role::FinanceOperator->value);

    $names = $role->fresh()->permissions->pluck('name')->all();

    expect($names)->toHaveCount(2)
        ->and($names)->toContain(Permission::DepositsApprove->value, Permission::DepositsConfirm->value)
        ->and(AuditEvent::where('action', 'role.permissions.update')->where('subject_id', $role->id)->exists())->toBeTrue();
});

it('refuses to strip the admin role of access management', function () {
    Sanctum::actingAs($this->admin);

    $role = RoleModel::where('name', Role::Admin->value)->firstOrFail();

    $this->putJson("/api/v1/admin/roles/{$role->id}/permissions", [
        'permissions' => [Permission::WalletsView->value],
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['permissions']]]]);

    expect($role->fresh()->permissions)->toHaveCount(count(Permission::cases()));
});

it('assigns roles to a user and records an audit event', function () {
    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/admin/users/{$this->customer->id}/roles", [
        'roles' => [Role::Auditor->value],
    ])->assertOk()->assertJsonPath('data.roles.0', Role::Auditor->value);

    expect($this->customer->fresh()->hasRole(Role::Auditor))->toBeTrue()
        ->and($this->customer->fresh()->hasRole(Role::Customer))->toBeFalse()
        ->and(AuditEvent::where('action', 'user.roles.update')->where('subject_id', $this->customer->id)->exists())->toBeTrue();
});

it('prevents removing the admin role from the last administrator', function () {
    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/admin/users/{$this->admin->id}/roles", [
        'roles' => [Role::Customer->value],
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['roles']]]]);

    expect($this->admin->fresh()->hasRole(Role::Admin))->toBeTrue();
});

it('allows demoting an admin when another administrator remains', function () {
    $other = User::factory()->create();
    $other->assignRole(Role::Admin->value);

    Sanctum::actingAs($other);

    $this->putJson("/api/v1/admin/users/{$this->admin->id}/roles", [
        'roles' => [Role::Auditor->value],
    ])->assertOk();

    expect($this->admin->fresh()->hasRole(Role::Admin))->toBeFalse();
});

it('sets direct permissions on a user', function () {
    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/admin/users/{$this->customer->id}/permissions", [
        'permissions' => [Permission::LedgerView->value],
    ])->assertOk();

    expect($this->customer->fresh()->hasPermissionTo(Permission::LedgerView))->toBeTrue()
        ->and(AuditEvent::where('action', 'user.permissions.update')->where('subject_id', $this->customer->id)->exists())->toBeTrue();

    $this->getJson("/api/v1/admin/users/{$this->customer->id}")
        ->assertOk()
        ->assertJsonPath('data.direct_permissions.0', Permission::LedgerView->value);
});

it('rejects unknown roles and permissions', function () {
    Sanctum::actingAs($this->admin);

    $customerRoleId = RoleModel::where('name', Role::Customer->value)->value('id');

    $this->putJson("/api/v1/admin/users/{$this->customer->id}/roles", ['roles' => ['nope']])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['roles.0']]]]);

    $this->putJson("/api/v1/admin/roles/{$customerRoleId}/permissions", ['permissions' => ['nope']])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['permissions.0']]]]);
});
