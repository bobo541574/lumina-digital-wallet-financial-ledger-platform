<?php

namespace App\Modules\Deposits\Services;

use App\Modules\Deposits\Enums\DepositChannel;
use App\Modules\Deposits\Enums\DepositStatus;
use App\Modules\Deposits\Exceptions\DepositInvalidStateException;
use App\Modules\Deposits\Exceptions\DepositSelfApprovalException;
use App\Modules\Deposits\Models\Deposit;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerPostingServiceInterface;
use App\Modules\Ledger\DTOs\JournalEntryData;
use App\Modules\Ledger\DTOs\JournalPostingData;
use App\Modules\Ledger\Enums\JournalEntryType;
use App\Modules\Ledger\Exceptions\LedgerAccountInvalidException;
use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Wallets\Exceptions\WalletNotFoundException;
use App\Modules\Wallets\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepositService
{
    public function __construct(private readonly LedgerPostingServiceInterface $ledger) {}

    public function createManual(User $user, string $walletId, int $amount, array $metadata = []): Deposit
    {
        $wallet = $this->ownedWallet($user, $walletId);

        return Deposit::create([
            'reference' => $this->reference(),
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'currency_code' => $wallet->currency_code,
            'amount' => $amount,
            'channel' => DepositChannel::Manual,
            'status' => DepositStatus::AwaitingApproval,
            'created_by' => $user->id,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }

    public function createProviderIntent(User $user, string $walletId, int $amount, string $provider): Deposit
    {
        $wallet = $this->ownedWallet($user, $walletId);

        return Deposit::create([
            'reference' => $this->reference(),
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'currency_code' => $wallet->currency_code,
            'amount' => $amount,
            'channel' => DepositChannel::Provider,
            'status' => DepositStatus::AwaitingPayment,
            'provider' => $provider,
            'provider_reference' => $this->reference('PAY'),
            'created_by' => $user->id,
            'occurred_at' => now(),
        ]);
    }

    public function approve(Deposit $deposit, User $operator): Deposit
    {
        if ($deposit->status !== DepositStatus::AwaitingApproval) {
            throw new DepositInvalidStateException($deposit, 'approved');
        }

        // Segregation of duties: the initiator may never approve their own request.
        if ($deposit->created_by === $operator->id) {
            throw new DepositSelfApprovalException($deposit->id);
        }

        $deposit->forceFill([
            'status' => DepositStatus::Approved,
            'approved_by' => $operator->id,
            'approved_at' => now(),
        ])->save();

        return $deposit;
    }

    public function reject(Deposit $deposit, User $operator): Deposit
    {
        if ($deposit->status !== DepositStatus::AwaitingApproval) {
            throw new DepositInvalidStateException($deposit, 'rejected');
        }

        $deposit->forceFill([
            'status' => DepositStatus::Failed,
            'failure_code' => 'REJECTED_BY_OPERATOR',
            'approved_by' => $operator->id,
        ])->save();

        return $deposit;
    }

    /**
     * Confirm funds received and post the ledger movement. Approval alone never
     * credits the wallet (approved != succeeded).
     */
    public function confirm(Deposit $deposit, User $operator): Deposit
    {
        if ($deposit->status !== DepositStatus::Approved) {
            throw new DepositInvalidStateException($deposit, 'confirmed');
        }

        return DB::transaction(function () use ($deposit, $operator): Deposit {
            $this->settle($deposit, 'CASH', $operator->id);

            return $deposit->refresh();
        });
    }

    public function cancel(Deposit $deposit, User $actor): Deposit
    {
        if ($deposit->status->isTerminal() || $deposit->status === DepositStatus::Approved) {
            throw new DepositInvalidStateException($deposit, 'cancelled');
        }

        $deposit->forceFill(['status' => DepositStatus::Cancelled])->save();

        return $deposit;
    }

    /**
     * Provider settlement entry point (invoked by the webhook pipeline).
     */
    public function markSucceededFromProvider(Deposit $deposit, ?string $providerTransactionId = null): Deposit
    {
        return DB::transaction(function () use ($deposit, $providerTransactionId): Deposit {
            $locked = Deposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'provider_transaction_id' => $providerTransactionId ?? $locked->provider_transaction_id,
            ])->save();

            if ($locked->status === DepositStatus::Succeeded) {
                return $locked;
            }

            $this->settle($locked, 'PROVIDER_CLEARING', null);

            return $locked->refresh();
        });
    }

    public function markFailed(Deposit $deposit, string $failureCode): Deposit
    {
        $deposit->forceFill([
            'status' => DepositStatus::Failed,
            'failure_code' => $failureCode,
        ])->save();

        return $deposit;
    }

    private function settle(Deposit $deposit, string $debitAccountCode, ?string $confirmedBy): void
    {
        $walletAccount = LedgerAccount::query()->where('wallet_id', $deposit->wallet_id)->first();
        $debitAccount = LedgerAccount::query()
            ->where('code', $debitAccountCode)
            ->whereNull('wallet_id')
            ->where('currency_code', $deposit->currency_code)
            ->first();

        if (! $walletAccount || ! $debitAccount) {
            throw new LedgerAccountInvalidException([
                'reason' => 'deposit_settlement_account_missing',
                'debit_account' => $debitAccountCode,
                'currency' => $deposit->currency_code,
            ]);
        }

        $result = $this->ledger->post(new JournalEntryData(
            currencyCode: $deposit->currency_code,
            entryType: JournalEntryType::Deposit,
            postings: [
                JournalPostingData::debit($debitAccount->id, $deposit->amount),
                JournalPostingData::credit($walletAccount->id, $deposit->amount, $deposit->wallet_id),
            ],
            sourceType: 'deposit',
            sourceId: $deposit->id,
            description: 'Deposit '.$deposit->reference,
            createdBy: $confirmedBy,
        ));

        $deposit->forceFill([
            'status' => DepositStatus::Succeeded,
            'journal_entry_id' => $result->journal->id,
            'settled_at' => now(),
            'confirmed_by' => $confirmedBy ?? $deposit->confirmed_by,
        ])->save();
    }

    private function ownedWallet(User $user, string $walletId): Wallet
    {
        $wallet = Wallet::query()
            ->where('id', $walletId)
            ->where('user_id', $user->id)
            ->first();

        if (! $wallet) {
            throw new WalletNotFoundException($walletId);
        }

        return $wallet;
    }

    private function reference(string $prefix = 'DEP'): string
    {
        return $prefix.strtoupper((string) Str::ulid());
    }
}
