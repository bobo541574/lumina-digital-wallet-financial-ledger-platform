<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\LoginData;
use App\Modules\Identity\DTOs\RegisterData;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Exceptions\InvalidTwoFactorCodeException;
use App\Modules\Identity\Exceptions\MfaRequiredException;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function register(RegisterData $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
            ]);

            $user->assignRole(Role::Customer->value);

            return $user;
        });
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(LoginData $data): array
    {
        $user = User::where('email', $data->email)->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            if (blank($data->twoFactorCode)) {
                throw new MfaRequiredException;
            }

            if (! $this->twoFactor->verify($user, $data->twoFactorCode)) {
                throw new InvalidTwoFactorCodeException;
            }
        }

        $token = $user->createToken($data->deviceName)->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token) {
            $token->delete();
        }
    }
}
