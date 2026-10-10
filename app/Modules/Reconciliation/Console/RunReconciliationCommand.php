<?php

namespace App\Modules\Reconciliation\Console;

use App\Modules\Reconciliation\Enums\ReconciliationLayer;
use App\Modules\Reconciliation\Enums\ReconciliationRunStatus;
use App\Modules\Reconciliation\Services\ReconciliationRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RunReconciliationCommand extends Command
{
    protected $signature = 'reconciliation:run
        {--layer=all : Layer to reconcile: wallet, provider, settlement or all}
        {--currency= : Restrict the run to a single currency}
        {--provider= : Restrict the run to a single provider}
        {--date= : Statement date (Y-m-d) for provider and settlement layers}';

    protected $description = 'Run wallet, provider and settlement reconciliation';

    public function handle(ReconciliationRunner $runner): int
    {
        $layer = (string) $this->option('layer');
        $scope = array_filter([
            'currency' => $this->option('currency'),
            'provider' => $this->option('provider'),
            'statement_date' => $this->option('date'),
        ], fn ($value) => $value !== null && $value !== '');

        if ($layer === 'all') {
            $runs = $runner->runAll($scope);
        } else {
            $resolved = ReconciliationLayer::tryFrom($layer);

            if ($resolved === null) {
                $this->error("Unknown layer [{$layer}]. Expected wallet, provider, settlement or all.");

                return self::FAILURE;
            }

            $runs = new Collection([$runner->run($resolved, $scope)]);
        }

        $failed = false;

        foreach ($runs as $run) {
            $this->line(sprintf(
                '%s: %s (%d discrepancies, %d items)',
                $run->layer->value,
                $run->status->value,
                $run->discrepancies_count,
                $run->items_count,
            ));

            if ($run->status === ReconciliationRunStatus::Failed) {
                $failed = true;
                $this->error("  error: {$run->error}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
