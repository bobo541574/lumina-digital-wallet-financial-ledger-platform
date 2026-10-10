<?php

namespace App\Modules\Reconciliation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'settlement_id', 'type', 'provider_transaction_id', 'reference', 'currency_code',
    'gross_amount', 'fee_amount', 'net_amount', 'matched', 'matched_type',
    'matched_id', 'difference', 'occurred_at',
])]
class ProviderSettlementLine extends Model
{
    use HasUuids;

    protected $table = 'provider_settlement_lines';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'fee_amount' => 'integer',
            'net_amount' => 'integer',
            'matched' => 'boolean',
            'difference' => 'integer',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ProviderSettlement::class, 'settlement_id');
    }
}
