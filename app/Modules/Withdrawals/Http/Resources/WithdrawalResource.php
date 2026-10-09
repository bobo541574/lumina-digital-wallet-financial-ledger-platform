<?php

namespace App\Modules\Withdrawals\Http\Resources;

use App\Modules\Withdrawals\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Withdrawal
 */
class WithdrawalResource extends JsonResource
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
            'wallet_id' => $this->wallet_id,
            'currency' => $this->currency_code,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'hold_id' => $this->hold_id,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'journal_entry_id' => $this->journal_entry_id,
            'failure_code' => $this->failure_code,
            'initiated_at' => $this->initiated_at?->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
