<?php

namespace App\Modules\Deposits\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class DepositSelfApprovalException extends DomainException
{
    public function __construct(string $depositId)
    {
        parent::__construct(
            ErrorCode::DepositSelfApproval,
            'An operator may not approve a deposit they initiated.',
            ['deposit_id' => $depositId],
        );
    }
}
