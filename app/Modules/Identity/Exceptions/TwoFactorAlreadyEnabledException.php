<?php

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class TwoFactorAlreadyEnabledException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(array $details = [])
    {
        parent::__construct(
            ErrorCode::TwoFactorAlreadyEnabled,
            'Two-factor authentication is already enabled.',
            $details,
        );
    }
}
