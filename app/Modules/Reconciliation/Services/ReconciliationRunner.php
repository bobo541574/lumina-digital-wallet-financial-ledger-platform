<?php

namespace App\Modules\Reconciliation\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Reconciliation\Enums\ReconciliationLayer;
use App\Modules\Reconciliation\Enums\ReconciliationRunStatus;
use App\Modules\Reconciliation\Models\ReconciliationRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates a reconciliation run: creates the run record, executes the
 * matching for the requested layer, and finalises the run. A single run covers a
 * single layer (see the `reconciliation_runs.layer` CHECK constraint); use
 * {@see runAll()} to reconcile every layer in one call.
 *
 * Failures are captured on the run (`status = failed`, `error`) rather than
 * thrown, so the admin surface can always display an outcome.
 */
class ReconciliationRunner
{
    public function __construct(
        private readonly WalletReconciliationService $wallet,
        private readonly ProviderReconciliationService $provider,
    ) {}

    /**
     * @param  array<string, mixed>  $scope
     */
    public function run(ReconciliationLayer $layer, array $scope = [], ?User $actor = null): ReconciliationRun
    {
        $run = ReconciliationRun::create([
            'layer' => $layer,
            'status' => ReconciliationRunStatus::Running,
            'scope' => $scope,
            'initiated_by' => $actor?->id,
            'started_at' => now(),
        ]);

        try {
            $discrepancies = match ($layer) {
                ReconciliationLayer::Wallet => $this->wallet->run($run, $scope),
                ReconciliationLayer::Provider => $this->provider->reconcileTransactions($run, $scope),
                ReconciliationLayer::Settlement => $this->provider->reconcileSettlements($run, $scope),
            };

            $run->forceFill([
                'status' => $discrepancies > 0
                    ? ReconciliationRunStatus::CompletedWithDiscrepancies
                    : ReconciliationRunStatus::Completed,
                'items_count' => $run->items()->count(),
                'discrepancies_count' => $discrepancies,
                'summary' => ['layer' => $layer->value, 'discrepancies' => $discrepancies],
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            Log::error('Reconciliation run failed.', [
                'run_id' => $run->id,
                'layer' => $layer->value,
                'exception' => $e,
            ]);

            $run->forceFill([
                'status' => ReconciliationRunStatus::Failed,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();
        }

        return $run;
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Collection<int, ReconciliationRun>
     */
    public function runAll(array $scope = [], ?User $actor = null): Collection
    {
        return collect(ReconciliationLayer::cases())
            ->map(fn (ReconciliationLayer $layer) => $this->run($layer, $scope, $actor));
    }
}
