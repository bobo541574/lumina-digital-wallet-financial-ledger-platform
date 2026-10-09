<?php

namespace App\Modules\Wallets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Wallets\Http\Resources\WalletResource;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Wallets\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWalletController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('wallets.manage');

        $wallets = Wallet::query()
            ->with('balance')
            ->when($request->query('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25);

        return ApiResponse::success(WalletResource::collection($wallets));
    }

    public function show(Wallet $wallet): JsonResponse
    {
        $this->authorize('wallets.manage');

        return ApiResponse::success(new WalletResource($wallet->load('balance')));
    }

    public function freeze(Request $request, Wallet $wallet): JsonResponse
    {
        $this->authorize('wallets.manage');

        $wallet = $this->wallets->freeze($wallet, $request->user());

        return ApiResponse::success(new WalletResource($wallet->load('balance')));
    }

    public function unfreeze(Request $request, Wallet $wallet): JsonResponse
    {
        $this->authorize('wallets.manage');

        $wallet = $this->wallets->unfreeze($wallet, $request->user());

        return ApiResponse::success(new WalletResource($wallet->load('balance')));
    }
}
