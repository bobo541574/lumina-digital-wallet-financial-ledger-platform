<?php

namespace App\Modules\Deposits\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class DepositNotFoundException extends DomainException
{
    public function __construct(?string $depositId = null)
    {
        parent::__construct(
            ErrorCode::DepositNotFound,
            'Deposit not found.',
            $depositId ? ['deposit_id' => $depositId] : [],
        );
    }
}
