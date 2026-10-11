<?php

namespace App\Modules\Notifications\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\UserNotification;

class UserNotificationPolicy
{
    public function view(User $user, UserNotification $notification): bool
    {
        return $notification->user_id === $user->id;
    }

    public function markRead(User $user, UserNotification $notification): bool
    {
        return $notification->user_id === $user->id;
    }
}
