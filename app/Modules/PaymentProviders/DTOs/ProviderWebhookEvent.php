<?php

namespace App\Modules\PaymentProviders\DTOs;

final readonly class ProviderWebhookEvent
{
    /**
     * @param  'succeeded'|'failed'|'processing'  $status
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $reference,
        public ?string $providerTransactionId,
        public int $amount,
        public string $currency,
        public string $status,
        public array $raw = [],
    ) {}
}
