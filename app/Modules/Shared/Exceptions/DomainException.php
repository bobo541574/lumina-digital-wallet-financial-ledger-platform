<?php

namespace App\Modules\Shared\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use RuntimeException;

/**
 * Base class for all expected, business-level failures.
 *
 * Rendering is handled centrally in bootstrap/app.php and produces the
 * envelope documented in docs/api-error-codes.md.
 */
class DomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message = '',
        public readonly array $details = [],
        ?int $httpStatus = null,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode->value);
        $this->httpStatus = $httpStatus ?? $errorCode->httpStatus();
    }

    public readonly int $httpStatus;
}
