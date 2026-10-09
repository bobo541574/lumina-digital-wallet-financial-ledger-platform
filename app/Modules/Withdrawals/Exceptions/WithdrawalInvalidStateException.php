<?php

namespace App\Modules\Withdrawals\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;
use App\Modules\Withdrawals\Models\Withdrawal;

class WithdrawalInvalidStateException extends DomainException
{
    public function __construct(Withdrawal $withdrawal, string $action)
    {
        parent::__construct(
            ErrorCode::WithdrawalInvalidState,
            "Withdrawal cannot be {$action} from status [{$withdrawal->status->value}].",
            ['withdrawal_id' => $withdrawal->id, 'status' => $withdrawal->status->value, 'action' => $action],
        );
    }
}
