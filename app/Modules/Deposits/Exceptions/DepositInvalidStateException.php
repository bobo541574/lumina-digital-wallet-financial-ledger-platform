<?php

namespace App\Modules\Deposits\Exceptions;

use App\Modules\Deposits\Models\Deposit;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class DepositInvalidStateException extends DomainException
{
    public function __construct(Deposit $deposit, string $action)
    {
        parent::__construct(
            ErrorCode::DepositInvalidState,
            "Deposit cannot be {$action} from status [{$deposit->status->value}].",
            ['deposit_id' => $deposit->id, 'status' => $deposit->status->value, 'action' => $action],
        );
    }
}
