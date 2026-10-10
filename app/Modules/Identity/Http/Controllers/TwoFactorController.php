<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Exceptions\InvalidTwoFactorCodeException;
use App\Modules\Identity\Exceptions\TwoFactorNotEnabledException;
use App\Modules\Identity\Http\Requests\TwoFactorCodeRequest;
use App\Modules\Identity\Services\TwoFactorService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function enable(Request $request): JsonResponse
    {
        return ApiResponse::success($this->twoFactor->begin($request->user()));
    }

    public function confirm(TwoFactorCodeRequest $request): JsonResponse
    {
        $codes = $this->twoFactor->confirm($request->user(), $request->validated('code'));

        return ApiResponse::success([
            'recovery_codes' => $codes,
            'message' => 'Two-factor authentication enabled.',
        ]);
    }

    public function disable(TwoFactorCodeRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            throw new TwoFactorNotEnabledException;
        }

        if (! $this->twoFactor->verify($user, $request->validated('code'))) {
            throw new InvalidTwoFactorCodeException;
        }

        $this->twoFactor->disable($user);

        return ApiResponse::success(['message' => 'Two-factor authentication disabled.']);
    }

    public function recoveryCodes(TwoFactorCodeRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->twoFactor->verify($user, $request->validated('code'))) {
            throw new InvalidTwoFactorCodeException;
        }

        return ApiResponse::success([
            'recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($user),
        ]);
    }
}
