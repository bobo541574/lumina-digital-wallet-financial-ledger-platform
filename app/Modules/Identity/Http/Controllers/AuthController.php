<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\DTOs\LoginData;
use App\Modules\Identity\DTOs\RegisterData;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Services\AuthService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register(RegisterData::fromArray($request->validated()));
        $token = $user->createToken('api')->plainTextToken;

        return ApiResponse::success([
            'user' => UserResource::make($user),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(LoginData::fromArray($request->validated()));

        return ApiResponse::success([
            'user' => UserResource::make($result['user']),
            'token' => $result['token'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(UserResource::make($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return ApiResponse::success(['message' => 'Logged out.']);
    }
}
