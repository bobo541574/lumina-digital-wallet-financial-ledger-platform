<?php

namespace App\Modules\PaymentProviders\DTOs;

final readonly class WebhookResult
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public int $status,
        public array $body,
    ) {}
}
