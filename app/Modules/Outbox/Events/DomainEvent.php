<?php

namespace App\Modules\Outbox\Events;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Generic, transport-agnostic domain event delivered from the transactional
 * outbox. Listeners subscribe by event type or handle every domain event.
 */
final readonly class DomainEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventType,
        public string $aggregateType,
        public string $aggregateId,
        public array $payload = [],
        public ?CarbonInterface $occurredAt = null,
    ) {}

    public function occurredAt(): CarbonInterface
    {
        return $this->occurredAt ?? CarbonImmutable::now();
    }
}
