<?php

namespace App\Modules\Reconciliation\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class ReconciliationItemAlreadyResolvedException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(array $details = [])
    {
        parent::__construct(
            ErrorCode::ReconciliationItemAlreadyResolved,
            'This reconciliation item has already been resolved.',
            $details,
        );
    }
}
