<?php

namespace App\Modules\PaymentProviders\Adapters;

use App\Modules\PaymentProviders\Contracts\CollectableProvider;
use App\Modules\PaymentProviders\Contracts\PaymentProviderInterface;
use App\Modules\PaymentProviders\DTOs\CollectRequest;
use App\Modules\PaymentProviders\DTOs\PaymentIntentResult;
use App\Modules\PaymentProviders\DTOs\ProviderWebhookEvent;
use App\Modules\PaymentProviders\Enums\PaymentStatus;

/**
 * Deterministic provider used for local development and tests. Webhooks are
 * signed with HMAC-SHA256 over the raw body using `payment.providers.fake.secret`
 * and sent in the `X-Signature` header.
 */
class FakeProvider implements CollectableProvider, PaymentProviderInterface
{
    public function __construct(private readonly string $secret) {}

    public function name(): string
    {
        return 'fake';
    }

    public function createPayment(CollectRequest $request): PaymentIntentResult
    {
        return new PaymentIntentResult(
            provider: 'fake',
            status: PaymentStatus::Processing,
            providerReference: $request->merchantReference,
            providerTransactionId: 'FAKE-'.$request->merchantReference,
            paymentUrl: null,
        );
    }

    public function queryStatus(string $providerTransactionId): PaymentIntentResult
    {
        return new PaymentIntentResult(
            provider: 'fake',
            status: PaymentStatus::Processing,
            providerReference: $providerTransactionId,
            providerTransactionId: $providerTransactionId,
        );
    }

    public function verifySignature(string $rawPayload, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $this->secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseEvent(array $payload): ProviderWebhookEvent
    {
        return new ProviderWebhookEvent(
            eventId: (string) ($payload['id'] ?? ''),
            eventType: (string) ($payload['type'] ?? 'payment'),
            reference: (string) ($payload['reference'] ?? ''),
            providerTransactionId: $payload['transaction_id'] ?? null,
            amount: (int) ($payload['amount'] ?? 0),
            currency: strtoupper((string) ($payload['currency'] ?? '')),
            status: (string) ($payload['status'] ?? 'processing'),
            raw: $payload,
        );
    }
}
