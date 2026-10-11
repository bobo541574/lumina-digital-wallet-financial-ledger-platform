<?php

namespace App\Modules\Ledger\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerPostingServiceInterface;
use App\Modules\Ledger\DTOs\JournalEntryData;
use App\Modules\Ledger\DTOs\JournalPostingData;
use App\Modules\Ledger\Enums\JournalEntryType;
use App\Modules\Ledger\Exceptions\JournalNotReversibleException;
use App\Modules\Ledger\Exceptions\ReversalAlreadyExistsException;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Results\PostedJournalResult;
use App\Modules\Outbox\Services\OutboxService;
use App\Modules\Shared\Support\ResilientTransaction;

/**
 * Posts a correction: an equal-and-opposite journal that exactly offsets the
 * original (see docs/business/accounting-rules.md "4.8 Correction / reversal"). The
 * original entry is never mutated; a journal may be reversed at most once.
 */
class LedgerReversalService
{
    public function __construct(
        private readonly LedgerPostingServiceInterface $ledger,
        private readonly OutboxService $outbox,
    ) {}

    public function reverse(JournalEntry $entry, ?User $actor = null, ?string $reason = null): PostedJournalResult
    {
        return ResilientTransaction::run(function () use ($entry, $actor, $reason): PostedJournalResult {
            $original = JournalEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($original->reverses_journal_entry_id !== null) {
                throw new JournalNotReversibleException([
                    'journal_entry_id' => $original->id,
                    'reason' => 'cannot_reverse_a_reversal',
                ]);
            }

            if ($original->isReversed()) {
                throw new ReversalAlreadyExistsException(['journal_entry_id' => $original->id]);
            }

            $postings = $original->postings->map(fn ($posting) => new JournalPostingData(
                ledgerAccountId: $posting->ledger_account_id,
                direction: $posting->direction->opposite(),
                amount: $posting->amount,
                walletId: $posting->wallet_id,
            ))->all();

            $result = $this->ledger->post(new JournalEntryData(
                currencyCode: $original->currency_code,
                entryType: JournalEntryType::Reversal,
                postings: $postings,
                sourceType: 'journal_reversal',
                sourceId: $original->id,
                description: $reason ?? 'Reversal of '.$original->id,
                reversesJournalEntryId: $original->id,
                createdBy: $actor?->id,
            ));

            $this->outbox->record('journal_entry', $result->journal->id, 'journal.reversed', [
                'journal_entry_id' => $result->journal->id,
                'reverses_journal_entry_id' => $original->id,
                'currency' => $original->currency_code,
            ]);

            return $result;
        });
    }
}
