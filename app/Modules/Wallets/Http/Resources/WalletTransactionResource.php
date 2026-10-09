<?php

namespace App\Modules\Wallets\Http\Resources;

use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\LedgerPosting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerPosting
 */
class WalletTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $signed = $this->direction->signedAmountFor(AccountType::Liability, $this->amount);

        return [
            'id' => $this->id,
            'journal_entry_id' => $this->journal_entry_id,
            'entry_type' => $this->journalEntry?->entry_type?->value,
            'source_type' => $this->journalEntry?->source_type,
            'source_id' => $this->journalEntry?->source_id,
            'description' => $this->journalEntry?->description,
            'direction' => $this->direction->value,
            'amount' => $this->amount,
            'signed_amount' => $signed,
            'currency' => $this->currency_code,
            'occurred_at' => $this->journalEntry?->occurred_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
