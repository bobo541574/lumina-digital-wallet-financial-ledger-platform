<?php

namespace App\Modules\Reconciliation\Http\Resources;

use App\Modules\Reconciliation\Models\ReconciliationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReconciliationItem
 */
class ReconciliationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_id' => $this->run_id,
            'layer' => $this->layer->value,
            'classification' => $this->classification->value,
            'status' => $this->status->value,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'provider' => $this->provider,
            'currency' => $this->currency_code,
            'difference' => $this->difference,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'resolution' => $this->resolution,
            'resolution_note' => $this->resolution_note,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
