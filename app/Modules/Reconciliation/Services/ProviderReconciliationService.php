<?php

namespace App\Modules\Reconciliation\Services;

use App\Modules\Deposits\Enums\DepositStatus;
use App\Modules\Deposits\Models\Deposit;
use App\Modules\Reconciliation\Enums\MismatchClassification;
use App\Modules\Reconciliation\Enums\ReconciliationLayer;
use App\Modules\Reconciliation\Models\ProviderSettlement;
use App\Modules\Reconciliation\Models\ProviderSettlementLine;
use App\Modules\Reconciliation\Models\ReconciliationItem;
use App\Modules\Reconciliation\Models\ReconciliationRun;
use App\Modules\Withdrawals\Enums\WithdrawalStatus;
use App\Modules\Withdrawals\Models\Withdrawal;
use Illuminate\Support\Collection;

/**
 * Layers 2 & 3 — Provider transaction and bank settlement reconciliation.
 *
 * `reconcileTransactions` matches each settlement line to our deposit/withdrawal
 * records by `provider_transaction_id` (falling back to our `reference`), then
 * verifies the amount and that the movement settled. Movements present in the
 * ledger but absent from the settlement (and vice versa) are classified
 * `missing`; repeated transaction ids are `duplicate`.
 *
 * `reconcileSettlements` checks that a settlement's declared net total equals the
 * sum of its lines. Nothing is ever auto-corrected.
 */
class ProviderReconciliationService
{
    /**
     * @param  array<string, mixed>  $scope
     */
    public function reconcileTransactions(ReconciliationRun $run, array $scope = []): int
    {
        $discrepancies = 0;

        foreach ($this->settlements($scope) as $settlement) {
            $discrepancies += $this->matchSettlement($run, $settlement);
        }

        return $discrepancies;
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function reconcileSettlements(ReconciliationRun $run, array $scope = []): int
    {
        $discrepancies = 0;

        foreach ($this->settlements($scope) as $settlement) {
            $expectedNet = (int) $settlement->lines->sum('net_amount');

            if ($expectedNet !== (int) $settlement->net_amount) {
                $this->item(
                    $run,
                    $settlement,
                    ReconciliationLayer::Settlement,
                    MismatchClassification::AmountMismatch,
                    'provider_settlement',
                    $settlement->id,
                    $expectedNet,
                    (int) $settlement->net_amount,
                );
                $settlement->forceFill(['status' => 'discrepancy'])->save();
                $discrepancies++;
            }
        }

        return $discrepancies;
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Collection<int, ProviderSettlement>
     */
    private function settlements(array $scope): Collection
    {
        return ProviderSettlement::query()
            ->with('lines')
            ->when($scope['provider'] ?? null, fn ($q, $provider) => $q->where('provider', $provider))
            ->when($scope['currency'] ?? null, fn ($q, $currency) => $q->where('currency_code', $currency))
            ->when($scope['statement_date'] ?? null, fn ($q, $date) => $q->whereDate('statement_date', $date))
            ->orderBy('statement_date')
            ->get();
    }

    private function matchSettlement(ReconciliationRun $run, ProviderSettlement $settlement): int
    {
        $from = $settlement->statement_date->startOfDay();
        $to = $settlement->statement_date->endOfDay();

        $deposits = Deposit::query()
            ->where('provider', $settlement->provider)
            ->whereBetween('occurred_at', [$from, $to])
            ->get();

        $withdrawals = Withdrawal::query()
            ->where('provider', $settlement->provider)
            ->whereBetween('occurred_at', [$from, $to])
            ->get();

        /** @var Collection<string, array{type: string, id: string, amount: int, settled: bool}> $byTransaction */
        $byTransaction = collect();
        $byReference = collect();

        foreach ($deposits as $deposit) {
            $entry = ['type' => 'deposit', 'id' => $deposit->id, 'amount' => $deposit->amount, 'settled' => $deposit->status === DepositStatus::Succeeded];
            if ($deposit->provider_transaction_id) {
                $byTransaction->put($deposit->provider_transaction_id, $entry);
            }
            $byReference->put($deposit->reference, $entry);
        }

        foreach ($withdrawals as $withdrawal) {
            $entry = ['type' => 'withdrawal', 'id' => $withdrawal->id, 'amount' => $withdrawal->amount, 'settled' => $withdrawal->status === WithdrawalStatus::Succeeded];
            if ($withdrawal->provider_transaction_id) {
                $byTransaction->put($withdrawal->provider_transaction_id, $entry);
            }
            $byReference->put($withdrawal->reference, $entry);
        }

        $discrepancies = 0;
        $lineTransactionIds = $settlement->lines->pluck('provider_transaction_id')->filter()->all();
        $seenTransactionIds = [];

        foreach ($settlement->lines as $line) {
            $transactionId = $line->provider_transaction_id;

            if ($transactionId !== null && in_array($transactionId, $seenTransactionIds, true)) {
                $this->item($run, $settlement, ReconciliationLayer::Provider, MismatchClassification::Duplicate, 'provider_transaction', null, 1, 2, ['provider_transaction_id' => $transactionId]);
                $discrepancies++;

                continue;
            }

            if ($transactionId !== null) {
                $seenTransactionIds[] = $transactionId;
            }

            $record = $transactionId !== null
                ? $byTransaction->get($transactionId)
                : ($line->reference !== null ? $byReference->get($line->reference) : null);

            if ($record === null) {
                $this->item($run, $settlement, ReconciliationLayer::Provider, MismatchClassification::Missing, 'provider_settlement_line', $line->id, null, $line->gross_amount);
                $discrepancies++;

                continue;
            }

            $difference = $record['amount'] - $line->gross_amount;

            $line->forceFill([
                'matched' => $difference === 0 && $record['settled'],
                'matched_type' => $record['type'],
                'matched_id' => $record['id'],
                'difference' => $difference,
            ])->save();

            if ($difference !== 0) {
                $this->item($run, $settlement, ReconciliationLayer::Provider, MismatchClassification::AmountMismatch, $record['type'], $record['id'], $record['amount'], $line->gross_amount);
                $discrepancies++;
            }

            if (! $record['settled']) {
                $this->item($run, $settlement, ReconciliationLayer::Provider, MismatchClassification::StatusMismatch, $record['type'], $record['id'], 'succeeded', 'unsettled');
                $discrepancies++;
            }
        }

        foreach ($deposits as $deposit) {
            $discrepancies += $this->flagMissingFromSettlement($run, $settlement, 'deposit', $deposit->id, $deposit->provider_transaction_id, $deposit->reference, $deposit->amount, $lineTransactionIds);
        }

        foreach ($withdrawals as $withdrawal) {
            $discrepancies += $this->flagMissingFromSettlement($run, $settlement, 'withdrawal', $withdrawal->id, $withdrawal->provider_transaction_id, $withdrawal->reference, $withdrawal->amount, $lineTransactionIds);
        }

        if ($discrepancies > 0) {
            $settlement->forceFill(['status' => 'discrepancy'])->save();
        } elseif ($settlement->status === 'received') {
            $settlement->forceFill(['status' => 'matched', 'reconciled_at' => now()])->save();
        }

        return $discrepancies;
    }

    /**
     * @param  list<string>  $lineTransactionIds
     */
    private function flagMissingFromSettlement(
        ReconciliationRun $run,
        ProviderSettlement $settlement,
        string $type,
        string $id,
        ?string $transactionId,
        ?string $reference,
        int $amount,
        array $lineTransactionIds,
    ): int {
        if ($transactionId !== null && in_array($transactionId, $lineTransactionIds, true)) {
            return 0;
        }

        if ($transactionId === null && $settlement->lines->contains(fn (ProviderSettlementLine $line) => $line->reference === $reference)) {
            return 0;
        }

        $this->item($run, $settlement, ReconciliationLayer::Provider, MismatchClassification::Missing, $type, $id, $amount, null);

        return 1;
    }

    /**
     * @param  array<string, mixed>  $extraActual
     */
    private function item(
        ReconciliationRun $run,
        ProviderSettlement $settlement,
        ReconciliationLayer $layer,
        MismatchClassification $classification,
        string $subjectType,
        ?string $subjectId,
        mixed $expected,
        mixed $actual,
        array $extraActual = [],
    ): void {
        ReconciliationItem::create([
            'run_id' => $run->id,
            'layer' => $layer,
            'classification' => $classification,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'provider' => $settlement->provider,
            'currency_code' => $settlement->currency_code,
            'difference' => is_int($expected) && is_int($actual) ? $expected - $actual : null,
            'expected' => ['value' => $expected],
            'actual' => ['value' => $actual, ...$extraActual],
        ]);
    }
}
