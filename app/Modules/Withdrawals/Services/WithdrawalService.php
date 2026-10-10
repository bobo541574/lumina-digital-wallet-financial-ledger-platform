<?php

namespace App\Modules\Withdrawals\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerPostingServiceInterface;
use App\Modules\Ledger\DTOs\JournalEntryData;
use App\Modules\Ledger\DTOs\JournalPostingData;
use App\Modules\Ledger\Enums\JournalEntryType;
use App\Modules\Ledger\Exceptions\LedgerAccountInvalidException;
use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Outbox\Services\OutboxService;
use App\Modules\PaymentProviders\Exceptions\ProviderRequestException;
use App\Modules\PaymentProviders\ProviderManager;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Idempotency\IdempotencyOutcome;
use App\Modules\Shared\Idempotency\IdempotencyResult;
use App\Modules\Shared\Idempotency\IdempotencyService;
use App\Modules\Shared\Support\ResilientTransaction;
use App\Modules\Wallets\Exceptions\WalletNotFoundException;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Withdrawals\DTOs\WithdrawalData;
use App\Modules\Withdrawals\Enums\WithdrawalStatus;
use App\Modules\Withdrawals\Exceptions\WithdrawalInvalidStateException;
use App\Modules\Withdrawals\Http\Resources\WithdrawalResource;
use App\Modules\Withdrawals\Models\Withdrawal;
use Illuminate\Support\Str;

class WithdrawalService
{
    public const IDEMPOTENCY_SCOPE = 'withdrawal.create';

    public function __construct(
        private readonly LedgerPostingServiceInterface $ledger,
        private readonly WalletHoldService $holds,
        private readonly ProviderManager $providers,
        private readonly IdempotencyService $idempotency,
        private readonly AuditLogger $audit,
        private readonly OutboxService $outbox,
    ) {}

    public function request(WithdrawalData $data): IdempotencyResult
    {
        return $this->idempotency->run(
            scope: self::IDEMPOTENCY_SCOPE,
            actorId: $data->actorId,
            key: $data->idempotencyKey,
            requestHash: $data->requestHash,
            callback: fn (): IdempotencyOutcome => $this->execute($data),
        );
    }

    private function execute(WithdrawalData $data): IdempotencyOutcome
    {
        return ResilientTransaction::run(function () use ($data): IdempotencyOutcome {
            $wallet = Wallet::query()
                ->where('id', $data->walletId)
                ->where('user_id', $data->actorId)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new WalletNotFoundException($data->walletId);
            }

            $withdrawal = Withdrawal::create([
                'reference' => 'WD'.strtoupper((string) Str::ulid()),
                'user_id' => $data->actorId,
                'wallet_id' => $wallet->id,
                'currency_code' => $wallet->currency_code,
                'amount' => $data->amount,
                'fee' => $data->fee,
                'status' => WithdrawalStatus::Pending,
                'provider' => $data->provider,
                'destination' => $data->destination,
                'requested_by' => $data->actorId,
                'occurred_at' => now(),
            ]);

            // Reserve the full outflow (amount + fee) so available balance drops
            // immediately and cannot be spent twice.
            $hold = $this->holds->reserve($wallet, $data->amount + $data->fee, $withdrawal->id);

            $withdrawal->forceFill([
                'status' => WithdrawalStatus::Reserved,
                'hold_id' => $hold->id,
            ])->save();

            $this->audit->record('withdrawal.request', 'withdrawal', $withdrawal->id, [], ['status' => 'reserved', 'amount' => $withdrawal->amount, 'fee' => $withdrawal->fee]);

            return new IdempotencyOutcome(
                body: (new WithdrawalResource($withdrawal->refresh()))->resolve(),
                status: 201,
                resourceType: 'withdrawal',
                resourceId: $withdrawal->id,
            );
        });
    }

    /**
     * Hand the reserved withdrawal to the payout provider.
     */
    public function initiate(Withdrawal $withdrawal, User $operator): Withdrawal
    {
        if ($withdrawal->status !== WithdrawalStatus::Reserved) {
            throw new WithdrawalInvalidStateException($withdrawal, 'initiated');
        }

        $provider = $this->providers->payoutProvider((string) $withdrawal->provider);

        try {
            $result = $provider->initiate($withdrawal);
        } catch (ProviderRequestException $e) {
            // A timeout means the payout may have been accepted on the provider
            // side. Keep the hold and hand the withdrawal to reconciliation
            // rather than releasing funds or failing the request.
            if ($e->errorCode === ErrorCode::ProviderTimeout) {
                return $this->markReconciling($withdrawal, $operator);
            }

            throw $e;
        }

        return ResilientTransaction::run(function () use ($withdrawal, $operator, $result): Withdrawal {
            $withdrawal->forceFill([
                'status' => $result->status,
                'provider_reference' => $result->providerReference ?? $withdrawal->provider_reference,
                'provider_transaction_id' => $result->providerTransactionId,
                'failure_code' => $result->failureCode,
                'processed_by' => $operator->id,
                'initiated_at' => now(),
            ])->save();

            $this->audit->record('withdrawal.initiate', 'withdrawal', $withdrawal->id, ['status' => 'reserved'], ['status' => $result->status->value], $operator);

            if ($result->status === WithdrawalStatus::Succeeded) {
                return $this->settleSucceeded($withdrawal, $result->providerTransactionId);
            }

            if ($result->status === WithdrawalStatus::Failed) {
                return $this->settleFailed($withdrawal, $result->failureCode ?? 'PROVIDER_REJECTED');
            }

            return $withdrawal;
        });
    }

    /**
     * Payout confirmed by the provider: post the ledger movement and consume the
     * hold. Terminal-safe and idempotent for a given withdrawal.
     */
    public function markSucceeded(Withdrawal $withdrawal, ?string $providerTransactionId = null): Withdrawal
    {
        return ResilientTransaction::run(function () use ($withdrawal, $providerTransactionId): Withdrawal {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === WithdrawalStatus::Succeeded) {
                return $locked;
            }

            if (! in_array($locked->status, [WithdrawalStatus::Processing, WithdrawalStatus::Reconciling, WithdrawalStatus::Reserved], true)) {
                throw new WithdrawalInvalidStateException($locked, 'settled');
            }

            return $this->settleSucceeded($locked, $providerTransactionId);
        });
    }

    /**
     * Payout definitively failed: release the hold (funds become available
     * again). Only call once the provider confirms no funds left the platform.
     */
    public function markFailed(Withdrawal $withdrawal, string $failureCode): Withdrawal
    {
        return ResilientTransaction::run(function () use ($withdrawal, $failureCode): Withdrawal {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === WithdrawalStatus::Failed) {
                return $locked;
            }

            if ($locked->status->isTerminal()) {
                throw new WithdrawalInvalidStateException($locked, 'failed');
            }

            return $this->settleFailed($locked, $failureCode);
        });
    }

    /**
     * Payout outcome unknown (timeout / no confirmation). Keep the hold and mark
     * for reconciliation; never release funds on an unverified failure.
     */
    public function markReconciling(Withdrawal $withdrawal, ?User $operator = null): Withdrawal
    {
        return ResilientTransaction::run(function () use ($withdrawal, $operator): Withdrawal {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [WithdrawalStatus::Processing, WithdrawalStatus::Reserved], true)) {
                throw new WithdrawalInvalidStateException($locked, 'marked reconciling');
            }

            $locked->forceFill([
                'status' => WithdrawalStatus::Reconciling,
                'failure_code' => null,
                'processed_by' => $operator?->id ?? $locked->processed_by,
                'initiated_at' => $locked->initiated_at ?? now(),
            ])->save();

            $this->audit->record(
                'withdrawal.reconciling',
                'withdrawal',
                $locked->id,
                ['status' => $withdrawal->status->value],
                ['status' => WithdrawalStatus::Reconciling->value],
                $operator,
            );

            return $locked;
        });
    }

    public function cancel(Withdrawal $withdrawal, User $actor): Withdrawal
    {
        return ResilientTransaction::run(function () use ($withdrawal, $actor): Withdrawal {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [WithdrawalStatus::Pending, WithdrawalStatus::Reserved], true)) {
                throw new WithdrawalInvalidStateException($locked, 'cancelled');
            }

            $this->releaseHold($locked);

            $locked->forceFill([
                'status' => WithdrawalStatus::Cancelled,
                'processed_by' => $actor->id,
            ])->save();

            $this->audit->record('withdrawal.cancel', 'withdrawal', $locked->id, ['status' => 'reserved'], ['status' => 'cancelled'], $actor);

            return $locked;
        });
    }

    private function settleSucceeded(Withdrawal $withdrawal, ?string $providerTransactionId): Withdrawal
    {
        if ($providerTransactionId !== null) {
            $withdrawal->forceFill(['provider_transaction_id' => $providerTransactionId])->save();
        }

        $walletAccount = LedgerAccount::query()->where('wallet_id', $withdrawal->wallet_id)->first();
        $payoutAccount = $this->systemAccount('PAYOUT_CLEARING', $withdrawal->currency_code);

        if (! $walletAccount || ! $payoutAccount) {
            throw new LedgerAccountInvalidException([
                'reason' => 'withdrawal_settlement_account_missing',
                'currency' => $withdrawal->currency_code,
            ]);
        }

        $postings = [
            JournalPostingData::debit($walletAccount->id, $withdrawal->amount + $withdrawal->fee, $withdrawal->wallet_id),
            JournalPostingData::credit($payoutAccount->id, $withdrawal->amount),
        ];

        if ($withdrawal->fee > 0) {
            $postings[] = JournalPostingData::credit(
                $this->systemAccount('FEE_REVENUE', $withdrawal->currency_code)->id,
                $withdrawal->fee,
            );
        }

        $result = $this->ledger->post(new JournalEntryData(
            currencyCode: $withdrawal->currency_code,
            entryType: JournalEntryType::Withdrawal,
            postings: $postings,
            sourceType: 'withdrawal',
            sourceId: $withdrawal->id,
            description: 'Withdrawal '.$withdrawal->reference,
            createdBy: null,
        ));

        if ($withdrawal->hold_id !== null) {
            $hold = $withdrawal->hold;
            if ($hold !== null && $hold->status->isActive()) {
                $this->holds->consume($hold);
            }
        }

        $withdrawal->forceFill([
            'status' => WithdrawalStatus::Succeeded,
            'journal_entry_id' => $result->journal->id,
            'settled_at' => now(),
        ])->save();

        $withdrawal = $withdrawal->refresh();

        $this->audit->record('withdrawal.settle', 'withdrawal', $withdrawal->id, [], ['status' => 'succeeded', 'journal_entry_id' => $withdrawal->journal_entry_id]);

        $this->outbox->record('withdrawal', $withdrawal->id, 'withdrawal.succeeded', [
            'withdrawal_id' => $withdrawal->id,
            'reference' => $withdrawal->reference,
            'wallet_id' => $withdrawal->wallet_id,
            'currency' => $withdrawal->currency_code,
            'amount' => $withdrawal->amount,
            'fee' => $withdrawal->fee,
            'provider' => $withdrawal->provider,
            'journal_entry_id' => $withdrawal->journal_entry_id,
        ]);

        return $withdrawal;
    }

    private function settleFailed(Withdrawal $withdrawal, string $failureCode): Withdrawal
    {
        $this->releaseHold($withdrawal);

        $withdrawal->forceFill([
            'status' => WithdrawalStatus::Failed,
            'failure_code' => $failureCode,
            'settled_at' => now(),
        ])->save();

        $withdrawal = $withdrawal->refresh();

        $this->audit->record('withdrawal.fail', 'withdrawal', $withdrawal->id, [], ['status' => 'failed', 'failure_code' => $failureCode]);

        $this->outbox->record('withdrawal', $withdrawal->id, 'withdrawal.failed', [
            'withdrawal_id' => $withdrawal->id,
            'reference' => $withdrawal->reference,
            'wallet_id' => $withdrawal->wallet_id,
            'currency' => $withdrawal->currency_code,
            'amount' => $withdrawal->amount,
            'provider' => $withdrawal->provider,
            'failure_code' => $failureCode,
        ]);

        return $withdrawal;
    }

    private function releaseHold(Withdrawal $withdrawal): void
    {
        if ($withdrawal->hold_id === null) {
            return;
        }

        $hold = $withdrawal->hold;
        if ($hold !== null && $hold->status->isActive()) {
            $this->holds->release($hold);
        }
    }

    private function systemAccount(string $code, string $currencyCode): ?LedgerAccount
    {
        return LedgerAccount::query()
            ->where('code', $code)
            ->whereNull('wallet_id')
            ->where('currency_code', $currencyCode)
            ->first();
    }
}
