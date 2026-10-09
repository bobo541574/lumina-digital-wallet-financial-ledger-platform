<?php

namespace App\Modules\Ledger\Enums;

enum JournalStatus: string
{
    case Posted = 'posted';
    case Reversed = 'reversed';
}
