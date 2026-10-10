<?php

namespace App\Modules\Reconciliation\Enums;

enum ReconciliationRunStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithDiscrepancies = 'completed_with_discrepancies';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this !== self::Running;
    }

    public function hasDiscrepancies(): bool
    {
        return in_array($this, [self::CompletedWithDiscrepancies, self::Failed], true);
    }
}
