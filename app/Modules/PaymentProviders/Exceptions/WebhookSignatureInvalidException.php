<?php

namespace App\Modules\PaymentProviders\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class WebhookSignatureInvalidException extends DomainException
{
    public function __construct(string $provider)
    {
        parent::__construct(
            ErrorCode::WebhookSignatureInvalid,
            'Webhook signature verification failed.',
            ['provider' => $provider],
        );
    }
}
