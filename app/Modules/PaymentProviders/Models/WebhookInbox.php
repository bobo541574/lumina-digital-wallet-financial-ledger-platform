<?php

namespace App\Modules\PaymentProviders\Models;

use App\Modules\Deposits\Models\Deposit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider', 'event_id', 'event_type', 'signature_valid', 'status',
    'payload', 'deposit_id', 'note', 'processed_at',
])]
class WebhookInbox extends Model
{
    use HasUuids;

    protected $table = 'webhook_inbox';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }
}
