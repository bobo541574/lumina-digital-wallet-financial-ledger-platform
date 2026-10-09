<?php

namespace App\Modules\PaymentProviders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PaymentProviders\Services\ProviderWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(private readonly ProviderWebhookService $webhooks) {}

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
