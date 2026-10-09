<?php

namespace App\Modules\PaymentProviders\Contracts;

use App\Modules\PaymentProviders\DTOs\ProviderWebhookEvent;

interface PaymentProviderInterface
{
    /**
     * Stable provider key, e.g. "fake".
     */
    public function name(): string;

    /**
     * Verify the webhook signature against the raw request body.
     */
    public function verifySignature(string $rawPayload, ?string $signature): bool;

    /**
     * Translate a verified provider payload into a normalized event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseEvent(array $payload): ProviderWebhookEvent;
}
