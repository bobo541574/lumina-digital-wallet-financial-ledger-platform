<?php

namespace App\Modules\Shared\Idempotency;

final readonly class IdempotencyResult
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public array $body,
        public int $status,
        public bool $replayed,
    ) {}
}
