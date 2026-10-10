<?php

namespace App\Modules\Outbox\Enums;

enum OutboxStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Processed || $this === self::Failed;
    }
}
