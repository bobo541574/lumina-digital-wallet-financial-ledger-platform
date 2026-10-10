<?php

namespace App\Modules\PaymentProviders\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a provider adapter is missing a required configuration value or
 * receives a request/response that cannot be parsed. This is a server-side /
 * integration error and never a user-facing validation failure.
 */
class ProviderConfigurationException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'PROVIDER_CONFIGURATION', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
