<?php

namespace App\Modules\Wallets\Http\Resources;

use App\Modules\Ledger\Models\LedgerPosting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property array{currency: string, from: string, to: string, opening_balance: int, closing_balance: int, movements: Collection<int, LedgerPosting>} $resource
 */
class WalletStatementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'currency' => $this->resource['currency'],
            'from' => $this->resource['from'],
            'to' => $this->resource['to'],
            'opening_balance' => $this->resource['opening_balance'],
            'closing_balance' => $this->resource['closing_balance'],
            'movements' => WalletTransactionResource::collection($this->resource['movements'])->resolve(),
        ];
    }
}
