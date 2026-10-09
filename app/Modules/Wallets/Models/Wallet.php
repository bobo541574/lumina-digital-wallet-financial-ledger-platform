<?php

namespace App\Modules\Wallets\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Wallets\Enums\WalletStatus;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'currency_code', 'name', 'status'])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): WalletFactory
    {
        return WalletFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WalletStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function balance(): HasOne
    {
        return $this->hasOne(WalletBalance::class);
    }

    public function ledgerAccount(): HasOne
    {
        return $this->hasOne(LedgerAccount::class);
    }
}
