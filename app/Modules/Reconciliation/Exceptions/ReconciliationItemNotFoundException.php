<?php

namespace App\Modules\Reconciliation\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class ReconciliationItemNotFoundException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(array $details = [])
    {
        parent::__construct(
            ErrorCode::ReconciliationItemNotFound,
            'The reconciliation item could not be found.',
            $details,
        );
    }
}
