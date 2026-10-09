<?php

namespace App\Modules\Transfers\Models;

use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Transfers\Enums\TransferStatus;
use App\Modules\Wallets\Models\Wallet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference', 'sender_wallet_id', 'recipient_wallet_id', 'currency_code',
    'amount', 'fee', 'status', 'failure_code', 'journal_entry_id',
    'initiated_by', 'occurred_at',
])]
class Transfer extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'amount' => 'integer',
            'fee' => 'integer',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function senderWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'sender_wallet_id');
    }

    public function recipientWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'recipient_wallet_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
