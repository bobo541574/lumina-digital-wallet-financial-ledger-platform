<?php

namespace App\Modules\Reconciliation\Http\Resources;

use App\Modules\Reconciliation\Models\ReconciliationRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReconciliationRun
 */
class ReconciliationRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'layer' => $this->layer->value,
            'status' => $this->status->value,
            'scope' => $this->scope,
            'summary' => $this->summary,
            'items_count' => $this->items_count,
            'discrepancies_count' => $this->discrepancies_count,
            'error' => $this->error,
            'initiated_by' => $this->initiated_by,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'items' => ReconciliationItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
