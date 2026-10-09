<?php

namespace App\Modules\Wallets\Http\Resources;

use App\Modules\Wallets\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wallet
 */
class WalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'currency' => $this->currency_code,
            'status' => $this->status->value,
            'balance' => $this->whenLoaded('balance', fn () => new WalletBalanceResource($this->balance)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
