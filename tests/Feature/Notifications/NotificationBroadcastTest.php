<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Events\NotificationCreated;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Outbox\Events\DomainEvent;
use App\Modules\Outbox\Services\OutboxProcessor;
use App\Modules\Outbox\Services\OutboxService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\WalletTestSupport;

uses(WalletTestSupport::class);

beforeEach(function () {
    $this->seedFinancials();
    $this->seed(RolePermissionSeeder::class);

    // The test suite broadcasts on the null driver by default; channel
    // authorization needs a real broadcaster, so point it at Reverb (the
    // Pusher protocol) with offline credentials that only exercise signing.
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb', [
        'driver' => 'reverb',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'app_id' => 'test-app',
        'options' => [
            'host' => 'localhost',
            'port' => 8080,
            'scheme' => 'http',
            'useTLS' => false,
        ],
        'client_options' => [],
    ]);

    Broadcast::purge('reverb');

    // Channels are bound at boot to the then-default (null) driver, so rebind
    // them to the reverb broadcaster for authorization to be evaluated.
    require base_path('routes/channels.php');

    $this->alice = User::factory()->create();
    $this->alice->assignRole(Role::Customer->value);
    $this->wallet = $this->openWallet($this->alice, 'USD');
});

it('broadcasts a realtime signal when a notification is recorded', function () {
    Event::fake([NotificationCreated::class]);

    DB::transaction(fn () => app(OutboxService::class)->record('deposit', (string) Str::uuid(), 'deposit.succeeded', [
        'deposit_id' => (string) Str::uuid(),
        'reference' => 'DEP-9',
        'wallet_id' => $this->wallet->id,
        'currency' => 'USD',
        'amount' => 5000,
    ]));

    app(OutboxProcessor::class)->process();

    Event::assertDispatchedTimes(NotificationCreated::class, 1);
});

it('does not rebroadcast when the same event is delivered twice', function () {
    Event::fake([NotificationCreated::class]);

    $event = new DomainEvent(
        eventType: 'deposit.succeeded',
        aggregateType: 'deposit',
        aggregateId: (string) Str::uuid(),
        payload: ['wallet_id' => $this->wallet->id, 'currency' => 'USD', 'amount' => 1000],
        id: (string) Str::uuid(),
    );

    event($event);
    event($event);

    Event::assertDispatchedTimes(NotificationCreated::class, 1);
});

it('broadcasts on the owning user private channel with a stable event name', function () {
    $notification = UserNotification::factory()->create(['user_id' => $this->alice->id]);
    $event = new NotificationCreated($notification);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastOn()->name)->toBe("private-App.Models.User.{$this->alice->id}")
        ->and($event->broadcastAs())->toBe('notification.created');
});

it('broadcasts a payload that mirrors the notification resource', function () {
    $notification = UserNotification::factory()->create([
        'user_id' => $this->alice->id,
        'type' => 'transfer.received',
        'title' => 'Money received',
        'body' => 'You received USD 25.00',
        'data' => ['reference' => 'TRF-9'],
    ]);

    $payload = (new NotificationCreated($notification))->broadcastWith();

    expect($payload)->toMatchArray([
        'id' => $notification->id,
        'type' => 'transfer.received',
        'title' => 'Money received',
        'body' => 'You received USD 25.00',
        'data' => ['reference' => 'TRF-9'],
        'read' => false,
    ]);
});

it('authorizes a user for their own private notification channel', function () {
    Sanctum::actingAs($this->alice);

    $this->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-App.Models.User.{$this->alice->id}",
    ])->assertOk()->assertJsonStructure(['auth']);
});

it('forbids authorizing another user private channel', function () {
    $bob = User::factory()->create();
    $bob->assignRole(Role::Customer->value);

    Sanctum::actingAs($this->alice);

    $this->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-App.Models.User.{$bob->id}",
    ])->assertForbidden();
});

it('requires authentication to authorize a channel', function () {
    $this->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-App.Models.User.{$this->alice->id}",
    ])->assertUnauthorized();
});
