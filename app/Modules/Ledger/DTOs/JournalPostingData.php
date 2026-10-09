<?php

namespace App\Modules\Ledger\DTOs;

use App\Modules\Ledger\Enums\PostingDirection;

final readonly class JournalPostingData
{
    public function __construct(
        public string $ledgerAccountId,
        public PostingDirection $direction,
        public int $amount,
        public ?string $walletId = null,
    ) {}

    public static function debit(string $ledgerAccountId, int $amount, ?string $walletId = null): self
    {
        return new self($ledgerAccountId, PostingDirection::Debit, $amount, $walletId);
    }

    public static function credit(string $ledgerAccountId, int $amount, ?string $walletId = null): self
    {
        return new self($ledgerAccountId, PostingDirection::Credit, $amount, $walletId);
    }
}
