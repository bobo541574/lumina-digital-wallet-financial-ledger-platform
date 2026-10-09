<?php

namespace App\Modules\Wallets\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['wallet_id', 'currency_code', 'posted_balance', 'reserved_balance'])]
class WalletBalance extends Model
{
    protected $primaryKey = 'wallet_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_balance' => 'integer',
            'reserved_balance' => 'integer',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function availableBalance(): int
    {
        return $this->posted_balance - $this->reserved_balance;
    }
}
