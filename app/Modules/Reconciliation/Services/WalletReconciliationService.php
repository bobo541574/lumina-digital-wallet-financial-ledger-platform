<?php

namespace App\Modules\Reconciliation\Services;

use App\Modules\Reconciliation\Enums\MismatchClassification;
use App\Modules\Reconciliation\Enums\ReconciliationLayer;
use App\Modules\Reconciliation\Models\ReconciliationItem;
use App\Modules\Reconciliation\Models\ReconciliationRun;
use Illuminate\Support\Facades\DB;

/**
 * Layer 1 — Wallet reconciliation (BLUEPRINT "Reconciliation layers").
 *
 * Recomputes each wallet's posted balance from `ledger_postings` and its
 * reserved balance from active `wallet_holds`, then compares both against the
 * cached `wallet_balances` projection. Any difference is recorded as a
 * discrepancy; nothing is ever auto-corrected.
 *
 * @see docs/accounting-rules.md ("Wallet balance projection")
 */
class WalletReconciliationService
{
    /**
     * @param  array<string, mixed>  $scope
     */
    public function run(ReconciliationRun $run, array $scope = []): int
    {
        $currency = $scope['currency'] ?? null;
        $discrepancies = 0;

        $balances = DB::table('wallet_balances')
            ->when($currency, fn ($q, $code) => $q->where('currency_code', $code))
            ->orderBy('wallet_id')
            ->get();

        foreach ($balances as $balance) {
            $expectedPosted = (int) DB::table('ledger_postings')
                ->where('wallet_id', $balance->wallet_id)
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) AS value")
                ->value('value');

            $expectedReserved = (int) DB::table('wallet_holds')
                ->where('wallet_id', $balance->wallet_id)
                ->where('status', 'active')
                ->sum('amount');

            if ($expectedPosted !== (int) $balance->posted_balance) {
                $this->record($run, $balance->wallet_id, $balance->currency_code, $expectedPosted, (int) $balance->posted_balance, 'posted_balance');
                $discrepancies++;
            }

            if ($expectedReserved !== (int) $balance->reserved_balance) {
                $this->record($run, $balance->wallet_id, $balance->currency_code, $expectedReserved, (int) $balance->reserved_balance, 'reserved_balance');
                $discrepancies++;
            }
        }

        return $discrepancies;
    }

    private function record(ReconciliationRun $run, string $walletId, ?string $currency, int $expected, int $actual, string $field): void
    {
        ReconciliationItem::create([
            'run_id' => $run->id,
            'layer' => ReconciliationLayer::Wallet,
            'classification' => MismatchClassification::AmountMismatch,
            'subject_type' => 'wallet_balance',
            'subject_id' => $walletId,
            'currency_code' => $currency,
            'difference' => $expected - $actual,
            'expected' => [$field => $expected],
            'actual' => [$field => $actual],
        ]);
    }
}
