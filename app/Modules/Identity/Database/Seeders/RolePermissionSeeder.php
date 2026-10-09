<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Enums\Permission as PermissionEnum;
use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionEnum::cases() as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission->value],
                ['label' => Str::headline($permission->value)],
            );
        }

        foreach (RoleEnum::cases() as $role) {
            $roleModel = Role::updateOrCreate(
                ['name' => $role->value],
                ['label' => Str::headline($role->value), 'is_system' => true],
            );

            $names = array_map(
                fn (PermissionEnum $permission) => $permission->value,
                PermissionEnum::forRole($role),
            );

            $roleModel->permissions()->sync(
                Permission::whereIn('name', $names)->pluck('id')->all(),
            );
        }
    }
}
