<?php

namespace App\Modules\Withdrawals\Models;

use App\Modules\Withdrawals\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use HasUuids;

    protected $table = 'withdrawals';

    protected $fillable = [
        'reference',
        'user_id',
        'wallet_id',
        'currency_code',
        'amount',
        'fee',
        'status',
        'hold_id',
        'provider',
        'destination',
        'provider_reference',
        'provider_transaction_id',
        'failure_code',
        'journal_entry_id',
        'requested_by',
        'processed_by',
        'initiated_at',
        'settled_at',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee' => 'integer',
            'status' => WithdrawalStatus::class,
            'destination' => 'array',
            'initiated_at' => 'datetime',
            'settled_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }

    public function hold(): BelongsTo
    {
        return $this->belongsTo(WalletHold::class, 'hold_id');
    }
}
