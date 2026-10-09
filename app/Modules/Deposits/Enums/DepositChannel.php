<?php

namespace App\Modules\Deposits\Enums;

enum DepositChannel: string
{
    case Manual = 'manual';
    case Provider = 'provider';
}
