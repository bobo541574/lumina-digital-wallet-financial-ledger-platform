<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\UpdateRolePermissionsRequest;
use App\Modules\Identity\Http\Requests\UpdateUserPermissionsRequest;
use App\Modules\Identity\Http\Requests\UpdateUserRolesRequest;
use App\Modules\Identity\Http\Resources\PermissionResource;
use App\Modules\Identity\Http\Resources\RoleResource;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccessControlService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAccessController extends Controller
{
    public function __construct(private readonly AccessControlService $access) {}

    public function roles(): JsonResponse
    {
        $this->authorize('roles.manage');

        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('label')
            ->get();

        return ApiResponse::success(RoleResource::collection($roles));
    }

    public function showRole(Role $role): JsonResponse
    {
        $this->authorize('roles.manage');

        return ApiResponse::success(
            new RoleResource($role->load('permissions')->loadCount('users')),
        );
    }

    public function permissions(): JsonResponse
    {
        $this->authorize('roles.manage');

        return ApiResponse::success(
            PermissionResource::collection(Permission::query()->orderBy('name')->get()),
        );
    }

    public function updateRolePermissions(UpdateRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $role = $this->access->syncRolePermissions(
            role: $role,
            permissions: $request->validated('permissions'),
            actor: $request->user(),
        );

        return ApiResponse::success(
            new RoleResource($role->load('permissions')->loadCount('users')),
        );
    }

    public function showUser(Request $request, User $user): JsonResponse
    {
        $this->authorize('users.manage');

        $user->load('roles');

        return ApiResponse::success([
            ...(new UserResource($user))->resolve($request),
            'direct_permissions' => $user->permissions()->pluck('name')->values(),
        ]);
    }

    public function updateUserRoles(UpdateUserRolesRequest $request, User $user): JsonResponse
    {
        $user = $this->access->syncUserRoles(
            user: $user,
            roles: $request->validated('roles'),
            actor: $request->user(),
        );

        return ApiResponse::success(new UserResource($user->load('roles')));
    }

    public function updateUserPermissions(UpdateUserPermissionsRequest $request, User $user): JsonResponse
    {
        $user = $this->access->syncUserPermissions(
            user: $user,
            permissions: $request->validated('permissions'),
            actor: $request->user(),
        );

        return ApiResponse::success(new UserResource($user->load('roles')));
    }
}
