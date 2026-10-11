<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Identity\Models\User;
use Database\Factories\UserNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A customer notification projected from a committed domain event. Rows are
 * immutable apart from `read_at`, and are deduplicated per user by `event_id`
 * so at-least-once event delivery never surfaces the same event twice.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $event_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property array<string, mixed>|null $data
 * @property Carbon|null $read_at
 */
#[Fillable(['user_id', 'event_id', 'type', 'title', 'body', 'data', 'read_at'])]
class UserNotification extends Model
{
    /** @use HasFactory<UserNotificationFactory> */
    use HasFactory, HasUuids;

    protected $table = 'user_notifications';

    protected static function newFactory(): UserNotificationFactory
    {
        return UserNotificationFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * @param  Builder<UserNotification>  $query
     */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * @param  Builder<UserNotification>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }
}
