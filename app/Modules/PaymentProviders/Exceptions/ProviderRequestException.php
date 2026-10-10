<?php

namespace App\Modules\PaymentProviders\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

/**
 * A provider HTTP call failed (transport error, timeout, non-2xx). Mapped to
 * PROVIDER_UNAVAILABLE / PROVIDER_TIMEOUT so callers can retry or reconcile.
 */
class ProviderRequestException extends DomainException
{
    public function __construct(string $provider, string $reason, bool $timeout = false)
    {
        parent::__construct(
            $timeout ? ErrorCode::ProviderTimeout : ErrorCode::ProviderUnavailable,
            "Payment provider [{$provider}] request failed: {$reason}",
            ['provider' => $provider, 'reason' => $reason],
        );
    }
}
