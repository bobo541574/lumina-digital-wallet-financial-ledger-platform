<?php

namespace App\Modules\Wallets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Transfers\Http\Resources\TransferResource;
use App\Modules\Transfers\Models\Transfer;
use App\Modules\Wallets\Http\Requests\OpenWalletRequest;
use App\Modules\Wallets\Http\Resources\WalletBalanceResource;
use App\Modules\Wallets\Http\Resources\WalletResource;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Wallets\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function index(Request $request): JsonResponse
    {
        $wallets = Wallet::query()
            ->where('user_id', $request->user()->id)
            ->with('balance')
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success(WalletResource::collection($wallets));
    }

    public function store(OpenWalletRequest $request): JsonResponse
    {
        $wallet = $this->wallets->open(
            $request->user(),
            $request->validated('currency'),
            $request->validated('name'),
        );

        $wallet->load('balance');

        return ApiResponse::success(new WalletResource($wallet), 201);
    }

    public function balance(Request $request, Wallet $wallet): JsonResponse
    {
        $this->authorize('view', $wallet);

        $balance = $wallet->balance ?? $this->wallets->ensureBalance($wallet);

        return ApiResponse::success(new WalletBalanceResource($balance));
    }

    public function transactions(Request $request, Wallet $wallet): JsonResponse
    {
        $this->authorize('view', $wallet);

        $transfers = Transfer::query()
            ->where(fn ($query) => $query
                ->where('sender_wallet_id', $wallet->id)
                ->orWhere('recipient_wallet_id', $wallet->id))
            ->orderByDesc('created_at')
            ->paginate(25);

        return ApiResponse::success(
            TransferResource::collection($transfers),
            meta: ['pagination' => [
                'current_page' => $transfers->currentPage(),
                'per_page' => $transfers->perPage(),
                'total' => $transfers->total(),
            ]],
        );
    }
}
