<?php

namespace App\Modules\Reconciliation\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Reconciliation\Enums\ReconciliationLayer;
use App\Modules\Reconciliation\Enums\ReconciliationRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'layer', 'status', 'scope', 'summary', 'items_count', 'discrepancies_count',
    'error', 'initiated_by', 'started_at', 'finished_at',
])]
class ReconciliationRun extends Model
{
    use HasUuids;

    protected $table = 'reconciliation_runs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layer' => ReconciliationLayer::class,
            'status' => ReconciliationRunStatus::class,
            'scope' => 'array',
            'summary' => 'array',
            'items_count' => 'integer',
            'discrepancies_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReconciliationItem::class, 'run_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
