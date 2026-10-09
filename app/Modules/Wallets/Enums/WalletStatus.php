<?php

namespace App\Modules\Wallets\Enums;

enum WalletStatus: string
{
    case Active = 'active';
    case Frozen = 'frozen';
    case Closed = 'closed';

    public function canDebit(): bool
    {
        return $this === self::Active;
    }

    public function canCredit(): bool
    {
        return $this !== self::Closed;
    }
}
