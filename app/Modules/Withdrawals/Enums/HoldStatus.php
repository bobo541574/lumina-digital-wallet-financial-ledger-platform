<?php

namespace App\Modules\Withdrawals\Enums;

enum HoldStatus: string
{
    case Active = 'active';
    case Consumed = 'consumed';
    case Released = 'released';
    case Expired = 'expired';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
