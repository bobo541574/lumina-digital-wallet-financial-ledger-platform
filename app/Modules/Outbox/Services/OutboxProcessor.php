<?php

namespace App\Modules\Outbox\Services;

use App\Modules\Outbox\Enums\OutboxStatus;
use App\Modules\Outbox\Events\DomainEvent;
use App\Modules\Outbox\Models\OutboxMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Claims pending outbox messages and delivers them as DomainEvent objects with
 * at-least-once semantics. Failed deliveries are retried with backoff until the
 * configured attempt limit, then parked as `failed` for reconciliation.
 *
 * Claiming uses `FOR UPDATE SKIP LOCKED`, so multiple workers can run safely
 * without delivering the same message twice.
 */
class OutboxProcessor
{
    public function process(int $limit = 100): int
    {
        $processed = 0;

        foreach ($this->claim($limit) as $message) {
            try {
                event(new DomainEvent(
                    eventType: $message->event_type,
                    aggregateType: $message->aggregate_type,
                    aggregateId: $message->aggregate_id,
                    payload: $message->payload ?? [],
                    id: $message->id,
                ));

                $message->forceFill([
                    'status' => OutboxStatus::Processed,
                    'processed_at' => now(),
                    'last_error' => null,
                ])->save();

                $processed++;
            } catch (Throwable $e) {
                $this->releaseOrFail($message, $e);
            }
        }

        return $processed;
    }

    /**
     * @return Collection<int, OutboxMessage>
     */
    private function claim(int $limit): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        return DB::transaction(function () use ($limit): Collection {
            $staleBefore = now()->subSeconds((int) config('outbox.processing_timeout', 300));

            $messages = OutboxMessage::query()
                ->where(function ($query) use ($staleBefore): void {
                    $query
                        ->where(function ($pending): void {
                            $pending->where('status', OutboxStatus::Pending)
                                ->where('available_at', '<=', now());
                        })
                        // Reclaim messages abandoned by a crashed worker.
                        ->orWhere(function ($stale) use ($staleBefore): void {
                            $stale->where('status', OutboxStatus::Processing)
                                ->where('updated_at', '<=', $staleBefore);
                        });
                })
                ->orderBy('available_at')
                ->orderBy('id')
                ->limit($limit)
                ->lock('for update skip locked')
                ->get();

            foreach ($messages as $message) {
                $message->forceFill(['status' => OutboxStatus::Processing])->save();
            }

            return $messages;
        });
    }

    private function releaseOrFail(OutboxMessage $message, Throwable $e): void
    {
        $attempts = $message->attempts + 1;
        $maxAttempts = (int) config('outbox.max_attempts', 5);

        $message->forceFill([
            'attempts' => $attempts,
            'last_error' => mb_substr($e->getMessage(), 0, 2000),
            'status' => $attempts >= $maxAttempts ? OutboxStatus::Failed : OutboxStatus::Pending,
            'available_at' => $attempts >= $maxAttempts
                ? $message->available_at
                : now()->addSeconds($this->backoffSeconds($attempts)),
        ])->save();
    }

    private function backoffSeconds(int $attempts): int
    {
        /** @var array<int, int> $schedule */
        $schedule = config('outbox.backoff', [5, 30, 120, 600]);

        if ($schedule === []) {
            return 0;
        }

        $index = min($attempts - 1, count($schedule) - 1);

        return (int) $schedule[$index];
    }
}
