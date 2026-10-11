<?php

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The `App.Models.User.{id}` channel mirrors Laravel's
| notification convention and is used to push in-app notifications to a single
| authenticated customer. Users are keyed by UUID, so ids are compared as
| strings and a user may only authorize their own channel.
|
*/

Broadcast::channel('App.Models.User.{userId}', function (User $user, string $userId): bool {
    return (string) $user->id === (string) $userId;
});
