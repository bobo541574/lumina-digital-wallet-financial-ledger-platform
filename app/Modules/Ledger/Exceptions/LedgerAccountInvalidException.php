<?php

namespace App\Modules\Ledger\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class LedgerAccountInvalidException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(array $details = [])
    {
        parent::__construct(
            ErrorCode::LedgerAccountInvalid,
            'One or more ledger accounts are unknown or inconsistent.',
            $details,
        );
    }
}
