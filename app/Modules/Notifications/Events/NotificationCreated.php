<?php

namespace App\Modules\Notifications\Events;

use App\Modules\Notifications\Http\Resources\UserNotificationResource;
use App\Modules\Notifications\Models\UserNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Signals a customer that a new in-app notification was recorded. Broadcast on
 * the user's private channel so the SPA can refresh immediately, but the event
 * payload is only a hint: the notification list and unread count stay
 * authoritative through the REST API.
 */
class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public UserNotification $notification) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->notification->user_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return UserNotificationResource::make($this->notification)->resolve(request());
    }
}
