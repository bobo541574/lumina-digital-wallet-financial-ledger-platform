<?php

namespace App\Modules\Reconciliation\Console;

use App\Modules\Reconciliation\Services\ReconciliationAlertService;
use Illuminate\Console\Command;

class AlertReconciliationCommand extends Command
{
    protected $signature = 'reconciliation:alert';

    protected $description = 'Report unresolved reconciliation discrepancies';

    public function handle(ReconciliationAlertService $alerts): int
    {
        $summary = $alerts->alert();

        if ($summary['total'] === 0) {
            $this->info('No unresolved reconciliation discrepancies.');

            return self::SUCCESS;
        }

        $this->warn("{$summary['total']} unresolved reconciliation discrepancy item(s).");

        foreach ($summary['by_layer'] as $layer => $count) {
            $this->line("  {$layer}: {$count}");
        }

        return self::SUCCESS;
    }
}
