<?php

namespace App\Modules\PaymentEvents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only provider/payment event log, partitioned monthly by
 * RANGE(occurred_at). The primary key is the composite `(id, occurred_at)`,
 * so records should be inserted and queried by time range rather than updated
 * by id alone.
 *
 * @property string $id
 * @property string $aggregate_type
 * @property string $aggregate_id
 * @property string $event_type
 * @property Carbon $occurred_at
 * @property array<string, mixed> $payload
 */
class PaymentEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'payment_events';

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
