<?php

namespace App\Modules\Ledger\Models;

use App\Modules\Ledger\Enums\JournalEntryType;
use App\Modules\Ledger\Enums\JournalStatus;
use App\Modules\Ledger\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'currency_code',
    'entry_type',
    'status',
    'source_type',
    'source_id',
    'description',
    'reverses_journal_entry_id',
    'occurred_at',
    'posted_at',
    'created_by',
])]
class JournalEntry extends Model
{
    use HasUuids, Immutable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_type' => JournalEntryType::class,
            'status' => JournalStatus::class,
            'occurred_at' => 'immutable_datetime',
            'posted_at' => 'immutable_datetime',
        ];
    }

    public function postings(): HasMany
    {
        return $this->hasMany(LedgerPosting::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_journal_entry_id');
    }

    public function reversal(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_journal_entry_id');
    }

    public function isReversed(): bool
    {
        return $this->reversal()->exists();
    }
}
