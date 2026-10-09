<?php

namespace App\Modules\Transfers\DTOs;

final readonly class TransferData
{
    public function __construct(
        public string $actorId,
        public string $senderWalletId,
        public string $recipientWalletId,
        public string $currencyCode,
        public int $amount,
        public int $fee,
        public string $idempotencyKey,
        public string $requestHash,
        public ?string $description = null,
    ) {}
}
