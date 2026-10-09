<?php

namespace App\Modules\Ledger\Models\Concerns;

use App\Modules\Ledger\Exceptions\LedgerImmutableException;

/**
 * Denies model-level updates and deletes. This is the application-layer guard;
 * PostgreSQL triggers provide the database-layer defense in depth.
 */
trait Immutable
{
    public static function bootImmutable(): void
    {
        static::updating(function (): void {
            throw new LedgerImmutableException;
        });

        static::deleting(function (): void {
            throw new LedgerImmutableException;
        });
    }
}
