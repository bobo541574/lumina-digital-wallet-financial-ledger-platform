<?php

namespace App\Modules\Identity\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Enums\Permission as PermissionEnum;
use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Privileged changes to roles and permissions. Every mutation is audited and
 * guarded against locking operators out of access management.
 */
class AccessControlService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Replace the roles assigned to a user.
     *
     * @param  array<int, string>  $roles
     */
    public function syncUserRoles(User $user, array $roles, User $actor): User
    {
        $this->guardLastAdmin($user, $roles);

        $before = $user->getRoleNames()->all();

        $user->syncRoles($roles);

        $this->audit->record(
            action: 'user.roles.update',
            subjectType: 'user',
            subjectId: $user->id,
            before: ['roles' => $before],
            after: ['roles' => array_values($roles)],
            actor: $actor,
        );

        return $user;
    }

    /**
     * Replace the direct (non-role) permissions granted to a user.
     *
     * @param  array<int, string>  $permissions
     */
    public function syncUserPermissions(User $user, array $permissions, User $actor): User
    {
        $before = $user->permissions()->pluck('name')->values()->all();

        $user->permissions()->sync(
            Permission::whereIn('name', $permissions)->pluck('id')->all(),
        );
        $user->forgetCachedPermissions();

        $this->audit->record(
            action: 'user.permissions.update',
            subjectType: 'user',
            subjectId: $user->id,
            before: ['permissions' => $before],
            after: ['permissions' => array_values($permissions)],
            actor: $actor,
        );

        return $user;
    }

    /**
     * Replace the permissions granted to a role.
     *
     * @param  array<int, string>  $permissions
     */
    public function syncRolePermissions(Role $role, array $permissions, User $actor): Role
    {
        $this->guardRoleLockout($role, $permissions);

        $before = $role->permissions()->pluck('name')->values()->all();

        $role->permissions()->sync(
            Permission::whereIn('name', $permissions)->pluck('id')->all(),
        );

        $this->audit->record(
            action: 'role.permissions.update',
            subjectType: 'role',
            subjectId: $role->id,
            before: ['permissions' => $before],
            after: ['permissions' => array_values($permissions)],
            actor: $actor,
        );

        return $role;
    }

    /**
     * Refuse to strip the admin role from the final administrator.
     *
     * @param  array<int, string>  $roles
     */
    private function guardLastAdmin(User $user, array $roles): void
    {
        $admin = RoleEnum::Admin->value;

        if (! $user->hasRole($admin) || in_array($admin, $roles, true)) {
            return;
        }

        $remaining = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', $admin))
            ->whereKeyNot($user->getKey())
            ->exists();

        if (! $remaining) {
            throw ValidationException::withMessages([
                'roles' => ['The last administrator cannot lose the admin role.'],
            ]);
        }
    }

    /**
     * The admin role must retain access-management so operators cannot lock
     * themselves out of the permission screen.
     *
     * @param  array<int, string>  $permissions
     */
    private function guardRoleLockout(Role $role, array $permissions): void
    {
        if ($role->name !== RoleEnum::Admin->value) {
            return;
        }

        if (! in_array(PermissionEnum::RolesManage->value, $permissions, true)) {
            throw ValidationException::withMessages([
                'permissions' => ['The admin role must keep the roles.manage permission.'],
            ]);
        }
    }
}
