<?php

namespace App\Modules\Ledger\Models;

use App\Modules\Ledger\Enums\PostingDirection;
use App\Modules\Ledger\Models\Concerns\Immutable;
use App\Modules\Wallets\Models\Wallet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'journal_entry_id',
    'ledger_account_id',
    'currency_code',
    'wallet_id',
    'direction',
    'amount',
])]
class LedgerPosting extends Model
{
    use HasUuids, Immutable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => PostingDirection::class,
            'amount' => 'integer',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
