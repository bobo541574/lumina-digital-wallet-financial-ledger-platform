<?php

namespace App\Modules\Deposits\Http\Resources;

use App\Modules\Deposits\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Deposit
 */
class DepositResource extends JsonResource
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
            'channel' => $this->channel->value,
            'wallet_id' => $this->wallet_id,
            'currency' => $this->currency_code,
            'amount' => $this->amount,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'provider_transaction_id' => $this->provider_transaction_id,
            'payment_url' => ($this->metadata ?? [])['payment_url'] ?? null,
            'qr_code' => ($this->metadata ?? [])['provider_data']['qr_code'] ?? null,
            'journal_entry_id' => $this->journal_entry_id,
            'failure_code' => $this->failure_code,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
