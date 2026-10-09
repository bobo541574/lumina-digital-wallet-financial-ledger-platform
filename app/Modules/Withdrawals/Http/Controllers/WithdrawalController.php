<?php

namespace App\Modules\Withdrawals\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Withdrawals\DTOs\WithdrawalData;
use App\Modules\Withdrawals\Http\Requests\CreateWithdrawalRequest;
use App\Modules\Withdrawals\Http\Resources\WithdrawalResource;
use App\Modules\Withdrawals\Models\Withdrawal;
use App\Modules\Withdrawals\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(private readonly WithdrawalService $withdrawals) {}

    public function index(Request $request): JsonResponse
    {
        $query = Withdrawal::query()->orderByDesc('created_at');

        if (! $request->user()->hasPermissionTo('withdrawals.process')) {
            $query->where('user_id', $request->user()->id);
        }

        return ApiResponse::success(WithdrawalResource::collection($query->get()));
    }

    public function store(CreateWithdrawalRequest $request): JsonResponse
    {
        $payload = $request->validated();
        ksort($payload);

        $result = $this->withdrawals->request(new WithdrawalData(
            actorId: $request->user()->id,
            walletId: $payload['wallet_id'],
            amount: (int) $payload['amount'],
            fee: (int) ($payload['fee'] ?? 0),
            provider: $payload['provider'],
            destination: $payload['destination'] ?? null,
            idempotencyKey: (string) $request->header('Idempotency-Key'),
            requestHash: hash('sha256', json_encode($payload)),
        ));

        return ApiResponse::success($result->body, $result->status);
    }

    public function show(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $this->authorize('view', $withdrawal);

        return ApiResponse::success(new WithdrawalResource($withdrawal));
    }

    public function initiate(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $this->authorize('withdrawals.process');

        $withdrawal = $this->withdrawals->initiate($withdrawal, $request->user());

        return ApiResponse::success(new WithdrawalResource($withdrawal));
    }

    public function cancel(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $this->authorize('cancel', $withdrawal);

        $withdrawal = $this->withdrawals->cancel($withdrawal, $request->user());

        return ApiResponse::success(new WithdrawalResource($withdrawal));
    }
}
