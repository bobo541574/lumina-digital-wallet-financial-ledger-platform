<?php

namespace App\Modules\Withdrawals\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Withdrawals\Services\PayoutWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutWebhookController extends Controller
{
    public function __construct(private readonly PayoutWebhookService $webhooks) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        $result = $this->webhooks->handle(
            $provider,
            $request->getContent(),
            $request->header('X-Signature'),
        );

        return response()->json($result->body, $result->status);
    }
}
