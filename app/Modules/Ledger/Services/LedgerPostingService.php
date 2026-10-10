<?php

namespace App\Modules\Ledger\Services;

use App\Modules\Ledger\Contracts\LedgerPostingServiceInterface;
use App\Modules\Ledger\DTOs\JournalEntryData;
use App\Modules\Ledger\Enums\JournalStatus;
use App\Modules\Ledger\Enums\PostingDirection;
use App\Modules\Ledger\Exceptions\InvalidPostingException;
use App\Modules\Ledger\Exceptions\LedgerAccountInactiveException;
use App\Modules\Ledger\Exceptions\LedgerAccountInvalidException;
use App\Modules\Ledger\Exceptions\LedgerCurrencyMismatchException;
use App\Modules\Ledger\Exceptions\LedgerDuplicateSourceException;
use App\Modules\Ledger\Exceptions\LedgerUnbalancedException;
use App\Modules\Ledger\Exceptions\ReversalAlreadyExistsException;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Ledger\Models\LedgerPosting;
use App\Modules\Ledger\Results\PostedJournalResult;
use App\Modules\Shared\Models\Currency;
use App\Modules\Shared\Support\ResilientTransaction;
use App\Modules\Wallets\Models\WalletBalance;
use Illuminate\Support\Collection;

class LedgerPostingService implements LedgerPostingServiceInterface
{
    public function post(JournalEntryData $entry): PostedJournalResult
    {
        $this->assertHasEnoughPostings($entry);
        $this->assertPositiveAmounts($entry);

        return ResilientTransaction::run(function () use ($entry): PostedJournalResult {
            /** @var Collection<string, LedgerAccount> $accounts */
            $accounts = $this->loadAccounts($entry);

            $this->assertCurrencyExists($entry);
            $this->assertAccountsActive($accounts);
            $this->assertCurrencyConsistency($entry, $accounts);
            $this->assertWalletConsistency($entry, $accounts);
            $this->assertBalanced($entry);
            $this->assertNoDuplicateSource($entry);
            $this->assertReversalValid($entry);

            $journal = JournalEntry::create([
                'currency_code' => $entry->currencyCode,
                'entry_type' => $entry->entryType,
                'status' => JournalStatus::Posted,
                'source_type' => $entry->sourceType,
                'source_id' => $entry->sourceId,
                'description' => $entry->description,
                'reverses_journal_entry_id' => $entry->reversesJournalEntryId,
                'occurred_at' => $entry->occurredAtOrNow(),
                'posted_at' => now(),
                'created_by' => $entry->createdBy,
            ]);

            $postings = [];

            foreach ($entry->postings as $data) {
                $postings[] = LedgerPosting::create([
                    'journal_entry_id' => $journal->id,
                    'ledger_account_id' => $data->ledgerAccountId,
                    'currency_code' => $entry->currencyCode,
                    'wallet_id' => $data->walletId,
                    'direction' => $data->direction,
                    'amount' => $data->amount,
                ]);
            }

            $this->projectWalletBalances($entry, $accounts);

            return new PostedJournalResult($journal, $postings);
        });
    }

    private function assertHasEnoughPostings(JournalEntryData $entry): void
    {
        if (count($entry->postings) < 2) {
            throw new InvalidPostingException('A journal entry requires at least two postings.');
        }
    }

    private function assertPositiveAmounts(JournalEntryData $entry): void
    {
        foreach ($entry->postings as $posting) {
            if ($posting->amount <= 0) {
                throw new InvalidPostingException('Posting amounts must be positive integers.', [
                    'ledger_account_id' => $posting->ledgerAccountId,
                    'amount' => $posting->amount,
                ]);
            }
        }
    }

    /**
     * @return Collection<string, LedgerAccount>
     */
    private function loadAccounts(JournalEntryData $entry): Collection
    {
        $ids = array_map(fn ($posting) => $posting->ledgerAccountId, $entry->postings);

        $accounts = LedgerAccount::whereIn('id', array_unique($ids))->get()->keyBy('id');

        $missing = array_values(array_diff(array_unique($ids), $accounts->keys()->all()));

        if ($missing !== []) {
            throw new LedgerAccountInvalidException(['missing_account_ids' => $missing]);
        }

        return $accounts;
    }

    private function assertCurrencyExists(JournalEntryData $entry): void
    {
        $currency = Currency::find($entry->currencyCode);

        if (! $currency || ! $currency->is_active) {
            throw new LedgerCurrencyMismatchException([
                'currency' => $entry->currencyCode,
                'reason' => 'unsupported_currency',
            ]);
        }
    }

    /**
     * @param  Collection<string, LedgerAccount>  $accounts
     */
    private function assertAccountsActive(Collection $accounts): void
    {
        foreach ($accounts as $account) {
            if (! $account->isActive()) {
                throw new LedgerAccountInactiveException([
                    'ledger_account_id' => $account->id,
                    'code' => $account->code,
                ]);
            }
        }
    }

    /**
     * @param  Collection<string, LedgerAccount>  $accounts
     */
    private function assertCurrencyConsistency(JournalEntryData $entry, Collection $accounts): void
    {
        foreach ($entry->postings as $posting) {
            /** @var LedgerAccount $account */
            $account = $accounts->get($posting->ledgerAccountId);

            if ($account->currency_code !== $entry->currencyCode) {
                throw new LedgerCurrencyMismatchException([
                    'journal_currency' => $entry->currencyCode,
                    'account_currency' => $account->currency_code,
                    'ledger_account_id' => $account->id,
                ]);
            }
        }
    }

    /**
     * @param  Collection<string, LedgerAccount>  $accounts
     */
    private function assertWalletConsistency(JournalEntryData $entry, Collection $accounts): void
    {
        foreach ($entry->postings as $posting) {
            /** @var LedgerAccount $account */
            $account = $accounts->get($posting->ledgerAccountId);

            if ($account->wallet_id !== null && $posting->walletId !== $account->wallet_id) {
                throw new LedgerAccountInvalidException([
                    'reason' => 'wallet_mismatch',
                    'ledger_account_id' => $account->id,
                    'expected_wallet_id' => $account->wallet_id,
                    'given_wallet_id' => $posting->walletId,
                ]);
            }

            if ($account->wallet_id === null && $posting->walletId !== null) {
                throw new LedgerAccountInvalidException([
                    'reason' => 'wallet_not_allowed',
                    'ledger_account_id' => $account->id,
                ]);
            }
        }
    }

    private function assertBalanced(JournalEntryData $entry): void
    {
        $debits = 0;
        $credits = 0;

        foreach ($entry->postings as $posting) {
            if ($posting->direction === PostingDirection::Debit) {
                $debits += $posting->amount;
            } else {
                $credits += $posting->amount;
            }
        }

        if ($debits !== $credits) {
            throw new LedgerUnbalancedException([
                'debits' => $debits,
                'credits' => $credits,
            ]);
        }
    }

    private function assertNoDuplicateSource(JournalEntryData $entry): void
    {
        if ($entry->sourceType === null || $entry->sourceId === null) {
            return;
        }

        $exists = JournalEntry::where('source_type', $entry->sourceType)
            ->where('source_id', $entry->sourceId)
            ->exists();

        if ($exists) {
            throw new LedgerDuplicateSourceException([
                'source_type' => $entry->sourceType,
                'source_id' => $entry->sourceId,
            ]);
        }
    }

    private function assertReversalValid(JournalEntryData $entry): void
    {
        if ($entry->reversesJournalEntryId === null) {
            return;
        }

        $original = JournalEntry::find($entry->reversesJournalEntryId);

        if (! $original) {
            throw new LedgerAccountInvalidException([
                'reason' => 'reversal_target_not_found',
                'journal_entry_id' => $entry->reversesJournalEntryId,
            ]);
        }

        $alreadyReversed = JournalEntry::where('reverses_journal_entry_id', $original->id)->exists();

        if ($alreadyReversed) {
            throw new ReversalAlreadyExistsException([
                'journal_entry_id' => $original->id,
            ]);
        }
    }

    /**
     * @param  Collection<string, LedgerAccount>  $accounts
     */
    private function projectWalletBalances(JournalEntryData $entry, Collection $accounts): void
    {
        $deltas = [];

        foreach ($entry->postings as $posting) {
            if ($posting->walletId === null) {
                continue;
            }

            /** @var LedgerAccount $account */
            $account = $accounts->get($posting->ledgerAccountId);

            $sign = $posting->direction->signedAmountFor($account->type, $posting->amount);

            $deltas[$posting->walletId] = ($deltas[$posting->walletId] ?? 0) + $sign;
        }

        foreach ($deltas as $walletId => $delta) {
            if ($delta === 0) {
                continue;
            }

            $balance = WalletBalance::where('wallet_id', $walletId)->lockForUpdate()->first();

            if (! $balance) {
                $balance = WalletBalance::create([
                    'wallet_id' => $walletId,
                    'currency_code' => $entry->currencyCode,
                    'posted_balance' => 0,
                    'reserved_balance' => 0,
                ]);
            }

            $balance->posted_balance += $delta;
            $balance->save();
        }
    }
}
