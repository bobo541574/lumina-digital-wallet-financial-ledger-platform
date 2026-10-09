<?php

namespace App\Modules\PaymentProviders\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class ProviderUnavailableException extends DomainException
{
    public function __construct(string $provider)
    {
        parent::__construct(
            ErrorCode::ProviderUnavailable,
            "Payment provider [{$provider}] is not configured.",
            ['provider' => $provider],
        );
    }
}
