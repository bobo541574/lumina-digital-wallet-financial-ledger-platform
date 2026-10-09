<?php

namespace App\Modules\Withdrawals\Models;

use App\Modules\Withdrawals\Enums\HoldStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletHold extends Model
{
    use HasUuids;

    protected $table = 'wallet_holds';

    protected $fillable = [
        'reference',
        'wallet_id',
        'currency_code',
        'amount',
        'status',
        'withdrawal_id',
        'expires_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => HoldStatus::class,
            'expires_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }
}
