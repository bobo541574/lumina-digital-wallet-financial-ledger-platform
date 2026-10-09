<?php

namespace App\Modules\Withdrawals\DTOs;

final readonly class WithdrawalData
{
    public function __construct(
        public string $actorId,
        public string $walletId,
        public int $amount,
        public int $fee,
        public string $provider,
        public ?array $destination,
        public string $idempotencyKey,
        public string $requestHash,
    ) {}
}
