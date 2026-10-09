<?php

namespace App\Modules\Wallets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ledger\Enums\JournalEntryType;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Wallets\Http\Requests\OpenWalletRequest;
use App\Modules\Wallets\Http\Resources\WalletBalanceResource;
use App\Modules\Wallets\Http\Resources\WalletResource;
use App\Modules\Wallets\Http\Resources\WalletStatementResource;
use App\Modules\Wallets\Http\Resources\WalletTransactionResource;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Wallets\Services\WalletHistoryService;
use App\Modules\Wallets\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly WalletHistoryService $history,
    ) {}

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

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', Rule::enum(JournalEntryType::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $postings = $this->history->history($wallet, $filters, (int) ($filters['per_page'] ?? 25));

        return ApiResponse::success(
            WalletTransactionResource::collection($postings),
            meta: ['pagination' => [
                'per_page' => $postings->perPage(),
                'next_cursor' => $postings->nextCursor()?->encode(),
                'prev_cursor' => $postings->previousCursor()?->encode(),
            ]],
        );
    }

    public function statement(Request $request, Wallet $wallet): JsonResponse
    {
        $this->authorize('view', $wallet);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($filters['from'])
            ? CarbonImmutable::parse($filters['from'])
            : CarbonImmutable::now()->subDays(30)->startOfDay();
        $to = isset($filters['to']) ? CarbonImmutable::parse($filters['to']) : CarbonImmutable::now();

        $statement = $this->history->statement($wallet, $from, $to);

        return ApiResponse::success(new WalletStatementResource($statement));
    }
}
