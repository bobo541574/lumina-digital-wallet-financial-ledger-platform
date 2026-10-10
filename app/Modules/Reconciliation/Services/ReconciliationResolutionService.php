<?php

namespace App\Modules\Reconciliation\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Reconciliation\Enums\ReconciliationItemStatus;
use App\Modules\Reconciliation\Exceptions\ReconciliationItemAlreadyResolvedException;
use App\Modules\Reconciliation\Exceptions\ReconciliationItemNotFoundException;
use App\Modules\Reconciliation\Models\ReconciliationItem;
use App\Modules\Shared\Support\ResilientTransaction;

/**
 * Records the human decision made about a reconciliation discrepancy. This is
 * bookkeeping only — balances and ledger entries are never mutated here. Any
 * corrective financial action (e.g. a ledger reversal) is a separate, explicit,
 * audited operation.
 */
class ReconciliationResolutionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $resolution
     */
    public function resolve(
        ReconciliationItem $item,
        User $actor,
        ReconciliationItemStatus $status,
        ?string $note = null,
        array $resolution = [],
    ): ReconciliationItem {
        return ResilientTransaction::run(function () use ($item, $actor, $status, $note, $resolution): ReconciliationItem {
            $locked = ReconciliationItem::query()->whereKey($item->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw new ReconciliationItemNotFoundException(['item_id' => $item->id]);
            }

            if (! $locked->status->isOpen()) {
                throw new ReconciliationItemAlreadyResolvedException([
                    'item_id' => $locked->id,
                    'status' => $locked->status->value,
                ]);
            }

            $locked->forceFill([
                'status' => $status,
                'resolution' => $resolution ?: null,
                'resolution_note' => $note,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ])->save();

            $this->audit->record(
                action: 'reconciliation.item_resolved',
                subjectType: 'reconciliation_item',
                subjectId: $locked->id,
                before: ['status' => ReconciliationItemStatus::Open->value],
                after: [
                    'status' => $status->value,
                    'resolution_note' => $note,
                    'classification' => $locked->classification->value,
                ],
                actor: $actor,
            );

            return $locked;
        });
    }
}
