<?php

namespace App\Modules\Transfers\Enums;

enum TransferStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
