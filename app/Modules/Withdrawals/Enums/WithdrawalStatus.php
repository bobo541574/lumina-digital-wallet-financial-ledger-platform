<?php

namespace App\Modules\Withdrawals\Enums;

enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Reserved = 'reserved';
    case Processing = 'processing';
    case Reconciling = 'reconciling';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Cancelled], true);
    }
}
