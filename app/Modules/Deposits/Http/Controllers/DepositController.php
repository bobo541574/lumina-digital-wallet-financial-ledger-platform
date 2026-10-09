<?php

namespace App\Modules\Deposits\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Deposits\Http\Requests\CreateDepositRequest;
use App\Modules\Deposits\Http\Requests\CreateProviderDepositRequest;
use App\Modules\Deposits\Http\Resources\DepositResource;
use App\Modules\Deposits\Models\Deposit;
use App\Modules\Deposits\Services\DepositService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Shared\Idempotency\IdempotencyOutcome;
use App\Modules\Shared\Idempotency\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Deposit::query()->orderByDesc('created_at');

        if (! $request->user()->hasPermissionTo('deposits.approve')) {
            $query->where('user_id', $request->user()->id);
        }

        return ApiResponse::success(DepositResource::collection($query->get()));
    }

    public function store(CreateDepositRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $metadata = $payload['metadata'] ?? [];
        ksort($payload);

        $result = $this->idempotency->run(
            scope: 'deposit.create.manual',
            actorId: $request->user()->id,
            key: $request->header('Idempotency-Key'),
            requestHash: hash('sha256', json_encode($payload)),
            callback: function () use ($request, $payload, $metadata): IdempotencyOutcome {
                $deposit = $this->deposits->createManual(
                    $request->user(),
                    $payload['wallet_id'],
                    (int) $payload['amount'],
                    $metadata,
                );

                return new IdempotencyOutcome(
                    body: (new DepositResource($deposit))->resolve(),
                    status: 201,
                    resourceType: 'deposit',
                    resourceId: $deposit->id,
                );
            },
        );

        return ApiResponse::success($result->body, $result->status);
    }

    public function storeProvider(CreateProviderDepositRequest $request): JsonResponse
    {
        $payload = $request->validated();
        ksort($payload);

        $result = $this->idempotency->run(
            scope: 'deposit.create.provider',
            actorId: $request->user()->id,
            key: $request->header('Idempotency-Key'),
            requestHash: hash('sha256', json_encode($payload)),
            callback: function () use ($request, $payload): IdempotencyOutcome {
                $deposit = $this->deposits->createProviderIntent(
                    $request->user(),
                    $payload['wallet_id'],
                    (int) $payload['amount'],
                    $payload['provider'],
                );

                return new IdempotencyOutcome(
                    body: (new DepositResource($deposit))->resolve(),
                    status: 201,
                    resourceType: 'deposit',
                    resourceId: $deposit->id,
                );
            },
        );

        return ApiResponse::success($result->body, $result->status);
    }

    public function show(Request $request, Deposit $deposit): JsonResponse
    {
        $this->authorize('view', $deposit);

        return ApiResponse::success(new DepositResource($deposit));
    }

    public function approve(Request $request, Deposit $deposit): JsonResponse
    {
        $this->authorize('deposits.approve');

        $deposit = $this->deposits->approve($deposit, $request->user());

        return ApiResponse::success(new DepositResource($deposit));
    }

    public function reject(Request $request, Deposit $deposit): JsonResponse
    {
        $this->authorize('deposits.approve');

        $deposit = $this->deposits->reject($deposit, $request->user());

        return ApiResponse::success(new DepositResource($deposit));
    }

    public function confirm(Request $request, Deposit $deposit): JsonResponse
    {
        $this->authorize('deposits.confirm');

        $deposit = $this->deposits->confirm($deposit, $request->user());

        return ApiResponse::success(new DepositResource($deposit));
    }
}
