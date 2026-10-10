<?php

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class MfaRequiredException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(array $details = [])
    {
        parent::__construct(
            ErrorCode::MfaRequired,
            'A two-factor authentication code is required.',
            $details,
        );
    }
}
