<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Events\NotificationCreated;
use App\Modules\Notifications\Models\UserNotification;

class NotificationService
{
    /**
     * Record a notification for a user. When an event id is supplied the write
     * is idempotent, so at-least-once event delivery creates at most one row.
     * A realtime signal is broadcast only when a new row is actually written.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(
        User $user,
        string $type,
        string $title,
        ?string $body = null,
        ?string $eventId = null,
        array $data = [],
    ): UserNotification {
        if ($eventId !== null) {
            $notification = UserNotification::query()->firstOrCreate(
                ['user_id' => $user->id, 'event_id' => $eventId],
                ['type' => $type, 'title' => $title, 'body' => $body, 'data' => $data ?: null],
            );
        } else {
            $notification = UserNotification::query()->create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data ?: null,
            ]);
        }

        if ($notification->wasRecentlyCreated) {
            NotificationCreated::dispatch($notification);
        }

        return $notification;
    }

    public function unreadCount(User $user): int
    {
        return UserNotification::query()->forUser($user)->unread()->count();
    }

    public function markRead(UserNotification $notification): UserNotification
    {
        if (! $notification->isRead()) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification;
    }

    public function markAllRead(User $user): int
    {
        return UserNotification::query()
            ->forUser($user)
            ->unread()
            ->update(['read_at' => now()]);
    }
}
