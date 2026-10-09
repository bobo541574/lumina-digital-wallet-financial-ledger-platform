<?php

namespace App\Modules\Transfers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Transfers\DTOs\TransferData;
use App\Modules\Transfers\Http\Requests\CreateTransferRequest;
use App\Modules\Transfers\Http\Resources\TransferResource;
use App\Modules\Transfers\Models\Transfer;
use App\Modules\Transfers\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(private readonly TransferService $transfers) {}

    public function store(CreateTransferRequest $request): JsonResponse
    {
        $payload = $request->validated();
        ksort($payload);

        $data = new TransferData(
            actorId: $request->user()->id,
            senderWalletId: $payload['sender_wallet_id'],
            recipientWalletId: $payload['recipient_wallet_id'],
            currencyCode: $payload['currency'],
            amount: (int) $payload['amount'],
            fee: 0,
            idempotencyKey: (string) $request->header('Idempotency-Key'),
            requestHash: hash('sha256', json_encode($payload)),
            description: $payload['description'] ?? null,
        );

        $result = $this->transfers->transfer($data);

        return ApiResponse::success($result->body, $result->status);
    }

    public function show(Request $request, Transfer $transfer): JsonResponse
    {
        $user = $request->user();

        $owns = $transfer->senderWallet?->user_id === $user->id
            || $transfer->recipientWallet?->user_id === $user->id;

        if (! $owns && ! $user->hasPermissionTo('ledger.view')) {
            throw new DomainException(ErrorCode::ResourceNotFound, 'Transfer not found.');
        }

        return ApiResponse::success(new TransferResource($transfer));
    }
}
