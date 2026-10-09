<?php

namespace App\Modules\Ledger\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
}
