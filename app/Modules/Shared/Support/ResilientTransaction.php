<?php

namespace App\Modules\Shared\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Runs a database transaction with automatic retries on transient concurrency
 * failures (deadlocks and serialization errors — PostgreSQL SQLSTATE 40001 /
 * 40P01). Laravel only re-attempts when the exception "caused by concurrency
 * error", so a genuinely failing transaction is not retried.
 *
 * Financial writes go through this so a losing race (e.g. two concurrent
 * transfers touching the same wallet) resolves as a retry rather than a 500.
 */
class ResilientTransaction
{
    public const DEFAULT_ATTEMPTS = 3;

    public static function run(Closure $callback, int $attempts = self::DEFAULT_ATTEMPTS): mixed
    {
        return DB::transaction($callback, max(1, $attempts));
    }
}
