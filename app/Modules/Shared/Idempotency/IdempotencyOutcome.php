<?php

namespace App\Modules\Shared\Idempotency;

/**
 * Result returned from an idempotent operation's callback. The body is stored
 * verbatim and replayed on subsequent requests with the same key.
 */
final readonly class IdempotencyOutcome
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public array $body,
        public int $status = 200,
        public ?string $resourceType = null,
        public ?string $resourceId = null,
    ) {}
}
