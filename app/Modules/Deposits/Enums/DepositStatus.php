<?php

namespace App\Modules\Deposits\Enums;

enum DepositStatus: string
{
    // Shared
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    // Manual flow
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';

    // Provider flow
    case AwaitingPayment = 'awaiting_payment';
    case Processing = 'processing';
    case Reconciling = 'reconciling';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Cancelled], true);
    }

    public function isSettled(): bool
    {
        return $this === self::Succeeded;
    }
}
