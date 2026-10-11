<?php

namespace App\Modules\Notifications\Providers;

use App\Modules\Notifications\Listeners\ProjectDomainEventToNotification;
use App\Modules\Outbox\Events\DomainEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(DomainEvent::class, ProjectDomainEventToNotification::class);
    }
}
