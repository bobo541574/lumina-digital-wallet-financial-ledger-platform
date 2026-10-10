<?php

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class TwoFactorNotEnabledException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(array $details = [])
    {
        parent::__construct(
            ErrorCode::TwoFactorNotEnabled,
            'Two-factor authentication has not been set up.',
            $details,
        );
    }
}
