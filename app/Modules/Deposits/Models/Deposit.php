<?php

namespace App\Modules\Deposits\Models;

use App\Modules\Deposits\Enums\DepositChannel;
use App\Modules\Deposits\Enums\DepositStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Wallets\Models\Wallet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference', 'user_id', 'wallet_id', 'currency_code', 'amount', 'channel',
    'status', 'provider', 'provider_reference', 'provider_transaction_id',
    'failure_code', 'journal_entry_id', 'created_by', 'approved_by',
    'confirmed_by', 'approved_at', 'settled_at', 'metadata', 'occurred_at',
])]
class Deposit extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => DepositChannel::class,
            'status' => DepositStatus::class,
            'amount' => 'integer',
            'approved_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'occurred_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
