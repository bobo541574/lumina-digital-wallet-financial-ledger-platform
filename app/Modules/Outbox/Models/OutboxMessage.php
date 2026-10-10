<?php

namespace App\Modules\Outbox\Models;

use App\Modules\Outbox\Enums\OutboxStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'aggregate_type', 'aggregate_id', 'event_type', 'payload',
    'status', 'attempts', 'available_at', 'processed_at', 'last_error',
])]
class OutboxMessage extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => OutboxStatus::class,
            'attempts' => 'integer',
            'available_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
