<?php

namespace App\Modules\Reconciliation\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Reconciliation\Enums\MismatchClassification;
use App\Modules\Reconciliation\Enums\ReconciliationItemStatus;
use App\Modules\Reconciliation\Enums\ReconciliationLayer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'run_id', 'layer', 'classification', 'subject_type', 'subject_id', 'provider',
    'currency_code', 'difference', 'expected', 'actual', 'status', 'resolution',
    'resolution_note', 'resolved_by', 'resolved_at',
])]
class ReconciliationItem extends Model
{
    use HasUuids;

    protected $table = 'reconciliation_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layer' => ReconciliationLayer::class,
            'classification' => MismatchClassification::class,
            'status' => ReconciliationItemStatus::class,
            'difference' => 'integer',
            'expected' => 'array',
            'actual' => 'array',
            'resolution' => 'array',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'run_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
