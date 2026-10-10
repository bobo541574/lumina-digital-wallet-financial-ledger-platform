<?php

namespace App\Modules\Outbox\Services;

use App\Modules\Outbox\Enums\OutboxStatus;
use App\Modules\Outbox\Exceptions\OutboxNotTransactionalException;
use App\Modules\Outbox\Models\OutboxMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Writes outbox messages. `record()` must be called from inside the same
 * database transaction as the financial write it announces, so either both
 * commit or neither does.
 */
class OutboxService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload = [],
        ?CarbonInterface $availableAt = null,
    ): OutboxMessage {
        if (DB::transactionLevel() === 0) {
            throw new OutboxNotTransactionalException;
        }

        return OutboxMessage::create([
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'payload' => $payload ?: null,
            'status' => OutboxStatus::Pending,
            'attempts' => 0,
            'available_at' => $availableAt ?? now(),
        ]);
    }
}
