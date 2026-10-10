<?php

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class InvalidTwoFactorCodeException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(array $details = [])
    {
        parent::__construct(
            ErrorCode::TwoFactorInvalidCode,
            'The two-factor authentication code is invalid.',
            $details,
        );
    }
}
