<?php

namespace App\Modules\Withdrawals\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class WithdrawalNotFoundException extends DomainException
{
    public function __construct(string $withdrawalId)
    {
        parent::__construct(
            ErrorCode::WithdrawalNotFound,
            'Withdrawal not found.',
            ['withdrawal_id' => $withdrawalId],
        );
    }
}
