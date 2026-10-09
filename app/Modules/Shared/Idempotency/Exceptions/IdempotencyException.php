<?php

namespace App\Modules\Shared\Idempotency\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class IdempotencyException extends DomainException
{
    public static function keyRequired(): self
    {
        return new self(ErrorCode::IdempotencyKeyRequired, 'An Idempotency-Key header is required for this request.');
    }

    public static function keyInvalid(): self
    {
        return new self(ErrorCode::IdempotencyKeyInvalid, 'The Idempotency-Key header must be between 8 and 255 characters.');
    }

    public static function reused(): self
    {
        return new self(ErrorCode::IdempotencyKeyReused, 'This Idempotency-Key was already used with a different request payload.');
    }

    public static function inProgress(): self
    {
        return new self(ErrorCode::IdempotencyInProgress, 'A request with this Idempotency-Key is still being processed.');
    }
}
