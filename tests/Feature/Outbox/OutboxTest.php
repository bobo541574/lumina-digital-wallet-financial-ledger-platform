<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Outbox\Enums\OutboxStatus;
use App\Modules\Outbox\Events\DomainEvent;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\Outbox\Services\OutboxProcessor;
use App\Modules\Outbox\Services\OutboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
    $this->fundWallet($this->wallet, 10000);

    $this->bob = User::factory()->create();
    $this->bob->assignRole(Role::Customer->value);
    $this->bobWallet = $this->openWallet($this->bob, 'USD');
});

it('records a transfer outbox message together with the money movement', function () {
    Sanctum::actingAs($this->alice);

    $transferId = $this->withHeaders(['Idempotency-Key' => 'outbox-transfer-1'])->postJson('/api/v1/transfers', [
        'sender_wallet_id' => $this->wallet->id,
        'recipient_wallet_id' => $this->bobWallet->id,
        'currency' => 'USD',
        'amount' => 3000,
    ])->assertCreated()->json('data.id');

    $message = OutboxMessage::query()->where('event_type', 'transfer.completed')->sole();

    expect($message->status)->toBe(OutboxStatus::Pending)
        ->and($message->aggregate_type)->toBe('transfer')
        ->and($message->aggregate_id)->toBe($transferId)
        ->and($message->payload['amount'])->toBe(3000);
});

it('writes no outbox message when the money movement is rejected', function () {
    Sanctum::actingAs($this->alice);

    $this->withHeaders(['Idempotency-Key' => 'outbox-transfer-fail'])->postJson('/api/v1/transfers', [
        'sender_wallet_id' => $this->wallet->id,
        'recipient_wallet_id' => $this->bobWallet->id,
        'currency' => 'USD',
        'amount' => 10001,
    ])->assertStatus(422)->assertJsonPath('error.code', 'INSUFFICIENT_FUNDS');

    expect(OutboxMessage::query()->count())->toBe(0);
});

it('delivers pending messages as domain events and marks them processed', function () {
    Event::fake([DomainEvent::class]);

    DB::transaction(fn () => app(OutboxService::class)
        ->record('transfer', (string) Str::uuid(), 'transfer.completed', ['amount' => 100]));

    $processed = app(OutboxProcessor::class)->process();

    expect($processed)->toBe(1);

    Event::assertDispatched(
        DomainEvent::class,
        fn (DomainEvent $event) => $event->eventType === 'transfer.completed' && $event->payload['amount'] === 100,
    );

    expect(OutboxMessage::query()->sole()->status)->toBe(OutboxStatus::Processed);
});

it('retries a failing delivery with backoff and parks it after the attempt limit', function () {
    config()->set('outbox.max_attempts', 2);
    config()->set('outbox.backoff', [1]);

    DB::transaction(fn () => app(OutboxService::class)
        ->record('transfer', (string) Str::uuid(), 'transfer.completed'));

    Event::listen(DomainEvent::class, fn () => throw new RuntimeException('boom'));

    $processor = app(OutboxProcessor::class);

    expect($processor->process())->toBe(0);

    $message = OutboxMessage::query()->sole();
    expect($message->status)->toBe(OutboxStatus::Pending)
        ->and($message->attempts)->toBe(1);

    $this->travel(2)->seconds();

    expect($processor->process())->toBe(0);

    $message->refresh();
    expect($message->status)->toBe(OutboxStatus::Failed)
        ->and($message->attempts)->toBe(2)
        ->and($message->last_error)->toContain('boom');
});

it('reclaims a message abandoned by a crashed worker', function () {
    Event::fake([DomainEvent::class]);

    DB::transaction(fn () => app(OutboxService::class)
        ->record('transfer', (string) Str::uuid(), 'transfer.completed'));

    $message = OutboxMessage::query()->sole();
    $message->forceFill([
        'status' => OutboxStatus::Processing,
        'updated_at' => now()->subMinutes(10),
    ])->save();

    expect(app(OutboxProcessor::class)->process())->toBe(1);

    expect(OutboxMessage::query()->sole()->status)->toBe(OutboxStatus::Processed);
    Event::assertDispatched(DomainEvent::class);
});
