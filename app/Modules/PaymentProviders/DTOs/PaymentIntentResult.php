<?php

namespace App\Modules\PaymentProviders\DTOs;

use App\Modules\PaymentProviders\Enums\PaymentStatus;

final readonly class PaymentIntentResult
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $provider,
        public PaymentStatus $status,
        public ?string $providerReference = null,
        public ?string $providerTransactionId = null,
        public ?string $paymentUrl = null,
        public array $extra = [],
        public array $raw = [],
    ) {}
}
