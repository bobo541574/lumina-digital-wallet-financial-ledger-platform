<?php

namespace App\Modules\Reconciliation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'provider', 'settlement_reference', 'currency_code', 'gross_amount',
    'fee_amount', 'net_amount', 'status', 'statement_date', 'raw', 'reconciled_at',
])]
class ProviderSettlement extends Model
{
    use HasUuids;

    protected $table = 'provider_settlements';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'fee_amount' => 'integer',
            'net_amount' => 'integer',
            'statement_date' => 'immutable_date',
            'raw' => 'array',
            'reconciled_at' => 'immutable_datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProviderSettlementLine::class, 'settlement_id');
    }
}
