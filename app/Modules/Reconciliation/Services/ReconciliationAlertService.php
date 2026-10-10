<?php

namespace App\Modules\Reconciliation\Services;

use App\Modules\Reconciliation\Enums\ReconciliationItemStatus;
use App\Modules\Reconciliation\Models\ReconciliationItem;
use Illuminate\Support\Facades\Log;

/**
 * Surfaces unresolved reconciliation discrepancies for operators and monitoring.
 * Used by the `reconciliation:alert` scheduled command.
 */
class ReconciliationAlertService
{
    /**
     * @return array{total: int, by_layer: array<string, int>, by_classification: array<string, int>}
     */
    public function summarize(): array
    {
        $open = ReconciliationItem::query()
            ->where('status', ReconciliationItemStatus::Open)
            ->get();

        return [
            'total' => $open->count(),
            'by_layer' => $open->groupBy(fn (ReconciliationItem $item) => $item->layer->value)
                ->map->count()
                ->all(),
            'by_classification' => $open->groupBy(fn (ReconciliationItem $item) => $item->classification->value)
                ->map->count()
                ->all(),
        ];
    }

    /**
     * @return array{total: int, by_layer: array<string, int>, by_classification: array<string, int>}
     */
    public function alert(): array
    {
        $summary = $this->summarize();

        if ($summary['total'] > 0) {
            Log::warning('Unresolved reconciliation discrepancies.', $summary);
        }

        return $summary;
    }
}
