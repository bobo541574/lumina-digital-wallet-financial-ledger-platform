<?php

namespace App\Modules\PaymentProviders\Enums;

/**
 * Normalized payment outcome used across provider adapters. Maps the many
 * provider-specific statuses to the three states the ledger pipeline knows.
 */
enum PaymentStatus: string
{
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}
