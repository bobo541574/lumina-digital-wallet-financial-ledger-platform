<?php

namespace App\Modules\Transfers\Http\Resources;

use App\Modules\Transfers\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transfer
 */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'sender_wallet_id' => $this->sender_wallet_id,
            'recipient_wallet_id' => $this->recipient_wallet_id,
            'currency' => $this->currency_code,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'journal_entry_id' => $this->journal_entry_id,
            'failure_code' => $this->failure_code,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
