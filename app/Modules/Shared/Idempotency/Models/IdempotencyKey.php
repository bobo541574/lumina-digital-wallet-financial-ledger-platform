<?php

namespace App\Modules\Shared\Idempotency\Models;

use App\Modules\Shared\Idempotency\IdempotencyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'scope', 'actor_id', 'key', 'request_hash', 'status', 'response_status',
    'response_body', 'resource_type', 'resource_id', 'locked_at', 'expires_at',
])]
class IdempotencyKey extends Model
{
    use HasUuids;

    protected $table = 'idempotency_keys';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IdempotencyStatus::class,
            'response_body' => 'array',
            'response_status' => 'integer',
            'locked_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
