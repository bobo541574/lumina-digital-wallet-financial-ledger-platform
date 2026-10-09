<?php

namespace App\Modules\Wallets\Http\Resources;

use App\Modules\Shared\Models\Currency;
use App\Modules\Wallets\Models\WalletBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WalletBalance
 */
class WalletBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = Currency::find($this->currency_code);

        return [
            'wallet_id' => $this->wallet_id,
            'currency' => $this->currency_code,
            'minor_units' => $currency?->minor_units ?? 0,
            'posted_balance' => $this->posted_balance,
            'reserved_balance' => $this->reserved_balance,
            'available_balance' => $this->posted_balance - $this->reserved_balance,
        ];
    }
}
