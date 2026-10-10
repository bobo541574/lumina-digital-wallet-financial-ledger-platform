<?php

namespace App\Modules\Reconciliation\Enums;

enum ReconciliationItemStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Ignored = 'ignored';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
