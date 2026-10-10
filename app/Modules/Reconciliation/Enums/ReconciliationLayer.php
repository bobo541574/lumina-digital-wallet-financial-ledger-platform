<?php

namespace App\Modules\Reconciliation\Enums;

/**
 * The three reconciliation layers (BLUEPRINT "Reconciliation layers").
 */
enum ReconciliationLayer: string
{
    case Wallet = 'wallet';
    case Provider = 'provider';
    case Settlement = 'settlement';

    public function label(): string
    {
        return match ($this) {
            self::Wallet => 'Wallet balance',
            self::Provider => 'Provider transactions',
            self::Settlement => 'Bank settlement',
        };
    }
}
