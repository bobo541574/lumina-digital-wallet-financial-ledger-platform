<?php

namespace App\Modules\Identity\Models\Concerns;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use BackedEnum;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * In-house role/permission support (replaces spatie/laravel-permission).
 *
 * Permissions reach a user either directly (permission_user) or via any of
 * their roles (role -> permission_role). Results are memoised per instance.
 */
trait HasRoles
{
    protected ?Collection $cachedRoleNames = null;

    protected ?Collection $cachedPermissionNames = null;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Direct permissions granted to this user.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function assignRole(string|BackedEnum ...$roles): static
    {
        $ids = Role::whereIn('name', $this->normalize($roles))->pluck('id');

        $this->roles()->syncWithoutDetaching($ids->all());
        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * @param  array<int, string|BackedEnum>  $roles
     */
    public function syncRoles(array $roles): static
    {
        $ids = Role::whereIn('name', $this->normalize($roles))->pluck('id');

        $this->roles()->sync($ids->all());
        $this->forgetCachedPermissions();

        return $this;
    }

    public function removeRole(string|BackedEnum ...$roles): static
    {
        $ids = Role::whereIn('name', $this->normalize($roles))->pluck('id');

        $this->roles()->detach($ids->all());
        $this->forgetCachedPermissions();

        return $this;
    }

    public function givePermissionTo(string|BackedEnum ...$permissions): static
    {
        $ids = Permission::whereIn('name', $this->normalize($permissions))->pluck('id');

        $this->permissions()->syncWithoutDetaching($ids->all());
        $this->forgetCachedPermissions();

        return $this;
    }

    public function revokePermissionTo(string|BackedEnum ...$permissions): static
    {
        $ids = Permission::whereIn('name', $this->normalize($permissions))->pluck('id');

        $this->permissions()->detach($ids->all());
        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * @param  string|BackedEnum|array<int, string|BackedEnum>  $roles
     */
    public function hasRole(string|BackedEnum|array $roles): bool
    {
        $names = $this->normalize(is_array($roles) ? $roles : [$roles]);

        return $this->getRoleNames()->intersect($names)->isNotEmpty();
    }

    /**
     * @param  string|BackedEnum|array<int, string|BackedEnum>  $permissions
     */
    public function hasPermissionTo(string|BackedEnum|array $permissions): bool
    {
        $names = $this->normalize(is_array($permissions) ? $permissions : [$permissions]);

        return $this->getPermissionNames()->intersect($names)->isNotEmpty();
    }

    /**
     * @param  array<int, string|BackedEnum>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return $this->hasPermissionTo($permissions);
    }

    /**
     * @return Collection<int, string>
     */
    public function getRoleNames(): Collection
    {
        return $this->cachedRoleNames ??= $this->roles()->pluck('name')->values();
    }

    /**
     * All effective permission names (via roles and direct).
     *
     * @return Collection<int, string>
     */
    public function getPermissionNames(): Collection
    {
        if ($this->cachedPermissionNames !== null) {
            return $this->cachedPermissionNames;
        }

        $viaRoles = $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name');

        $direct = $this->permissions()->pluck('name');

        return $this->cachedPermissionNames = $viaRoles
            ->merge($direct)
            ->unique()
            ->values();
    }

    public function forgetCachedPermissions(): void
    {
        $this->cachedRoleNames = null;
        $this->cachedPermissionNames = null;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    protected function normalize(array $items): array
    {
        return array_map(
            fn ($item) => $item instanceof BackedEnum ? (string) $item->value : (string) $item,
            $items,
        );
    }
}
