<?php

namespace App\Modules\Ledger\Enums;

enum JournalEntryType: string
{
    case Transfer = 'transfer';
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Fee = 'fee';
    case Reversal = 'reversal';
    case Adjustment = 'adjustment';
}
