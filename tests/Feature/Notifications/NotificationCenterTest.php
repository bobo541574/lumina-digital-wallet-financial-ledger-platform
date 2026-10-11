<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Outbox\Events\DomainEvent;
use App\Modules\Outbox\Services\OutboxProcessor;
use App\Modules\Outbox\Services\OutboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\WalletTestSupport;

uses(WalletTestSupport::class);

beforeEach(function () {
    $this->seedFinancials();
    $this->seed(RolePermissionSeeder::class);

    $this->alice = User::factory()->create();
    $this->alice->assignRole(Role::Customer->value);
    $this->wallet = $this->openWallet($this->alice, 'USD');
});

it('projects a committed deposit event into a single owner notification', function () {
    DB::transaction(fn () => app(OutboxService::class)->record('deposit', (string) Str::uuid(), 'deposit.succeeded', [
        'deposit_id' => (string) Str::uuid(),
        'reference' => 'DEP-1',
        'wallet_id' => $this->wallet->id,
        'currency' => 'USD',
        'amount' => 5000,
    ]));

    app(OutboxProcessor::class)->process();

    $notification = UserNotification::query()->sole();

    expect($notification->user_id)->toBe($this->alice->id)
        ->and($notification->type)->toBe('deposit.succeeded')
        ->and($notification->title)->toBe('Deposit received')
        ->and($notification->body)->toContain('USD 50.00')
        ->and($notification->isRead())->toBeFalse();
});

it('does not duplicate a notification when the same event is delivered twice', function () {
    $event = new DomainEvent(
        eventType: 'deposit.succeeded',
        aggregateType: 'deposit',
        aggregateId: (string) Str::uuid(),
        payload: ['wallet_id' => $this->wallet->id, 'currency' => 'USD', 'amount' => 1000],
        id: (string) Str::uuid(),
    );

    event($event);
    event($event);

    expect(UserNotification::query()->count())->toBe(1);
});

it('notifies both wallets when a transfer completes', function () {
    $bob = User::factory()->create();
    $bob->assignRole(Role::Customer->value);
    $bobWallet = $this->openWallet($bob, 'USD');

    DB::transaction(fn () => app(OutboxService::class)->record('transfer', (string) Str::uuid(), 'transfer.completed', [
        'reference' => 'TRF-1',
        'sender_wallet_id' => $this->wallet->id,
        'recipient_wallet_id' => $bobWallet->id,
        'currency' => 'USD',
        'amount' => 2500,
    ]));

    app(OutboxProcessor::class)->process();

    expect(UserNotification::query()->where('user_id', $this->alice->id)->where('type', 'transfer.sent')->count())->toBe(1)
        ->and(UserNotification::query()->where('user_id', $bob->id)->where('type', 'transfer.received')->count())->toBe(1);
});

it('lists only the acting user notifications with an unread count', function () {
    UserNotification::factory()->count(2)->create(['user_id' => $this->alice->id]);
    UserNotification::factory()->create(['user_id' => User::factory()->create()->id]);

    Sanctum::actingAs($this->alice);

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.unread_count', 2);
});

it('filters the list to unread notifications', function () {
    UserNotification::factory()->create(['user_id' => $this->alice->id]);
    UserNotification::factory()->read()->create(['user_id' => $this->alice->id]);

    Sanctum::actingAs($this->alice);

    $this->getJson('/api/v1/notifications?unread=1')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns the unread count', function () {
    UserNotification::factory()->count(3)->create(['user_id' => $this->alice->id]);
    UserNotification::factory()->read()->create(['user_id' => $this->alice->id]);

    Sanctum::actingAs($this->alice);

    $this->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 3);
});

it('marks a single notification as read', function () {
    $notification = UserNotification::factory()->create(['user_id' => $this->alice->id]);

    Sanctum::actingAs($this->alice);

    $this->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.read', true);

    expect($notification->refresh()->isRead())->toBeTrue();
});

it('marks all notifications as read', function () {
    UserNotification::factory()->count(3)->create(['user_id' => $this->alice->id]);

    Sanctum::actingAs($this->alice);

    $this->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.updated', 3);

    expect(UserNotification::query()->where('user_id', $this->alice->id)->unread()->count())->toBe(0);
});

it('forbids marking another user notification as read', function () {
    $notification = UserNotification::factory()->create([
        'user_id' => User::factory()->create()->id,
    ]);

    Sanctum::actingAs($this->alice);

    $this->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertStatus(403);
});

it('requires authentication to view notifications', function () {
    $this->getJson('/api/v1/notifications')->assertStatus(401);
});
