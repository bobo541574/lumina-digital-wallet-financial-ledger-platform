<?php

namespace App\Modules\Ledger\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class LedgerAccountInactiveException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(array $details = [])
    {
        parent::__construct(
            ErrorCode::LedgerAccountInactive,
            'Cannot post to an inactive ledger account.',
            $details,
        );
    }
}
