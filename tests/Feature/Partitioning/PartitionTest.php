<?php

use App\Modules\PaymentEvents\Models\PaymentEvent;
use App\Modules\PaymentEvents\Partitioning\PartitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function insertPaymentEvent(CarbonImmutable $occurredAt): void
{
    PaymentEvent::create([
        'aggregate_type' => 'deposit',
        'aggregate_id' => (string) Str::uuid(),
        'event_type' => 'deposit.succeeded',
        'occurred_at' => $occurredAt,
        'payload' => ['amount' => 100],
    ]);
}

it('pre-creates monthly partitions and is idempotent', function () {
    $manager = app(PartitionManager::class);
    $from = CarbonImmutable::parse('2026-10-01 00:00:00');

    $created = $manager->ensure('payment_events', 'occurred_at', monthsAhead: 2, from: $from);

    expect($created)->toBe([
        'payment_events_2026_10',
        'payment_events_2026_11',
        'payment_events_2026_12',
    ]);

    expect($manager->ensure('payment_events', 'occurred_at', monthsAhead: 2, from: $from))->toBe([])
        ->and($manager->missing('payment_events', monthsAhead: 2, from: $from))->toBe([]);
});

it('reports months that still need a partition', function () {
    $manager = app(PartitionManager::class);
    $from = CarbonImmutable::parse('2026-10-01 00:00:00');

    $manager->ensure('payment_events', 'occurred_at', monthsAhead: 0, from: $from);

    expect($manager->missing('payment_events', monthsAhead: 2, from: $from))->toBe([
        'payment_events_2026_11',
        'payment_events_2026_12',
    ]);
});

it('moves rows out of the default partition when a monthly partition is created', function () {
    $manager = app(PartitionManager::class);
    $october = CarbonImmutable::parse('2026-10-15 12:00:00');

    insertPaymentEvent($october);

    expect(DB::table('payment_events_default')->count())->toBe(1);

    $manager->createPartition('payment_events', 'occurred_at', CarbonImmutable::parse('2026-10-01'));

    expect(DB::table('payment_events_default')->count())->toBe(0)
        ->and(DB::table('payment_events_2026_10')->count())->toBe(1);
});

it('prunes unrelated partitions for a timestamp range query', function () {
    $manager = app(PartitionManager::class);
    $manager->createPartition('payment_events', 'occurred_at', CarbonImmutable::parse('2026-09-01'));
    $manager->createPartition('payment_events', 'occurred_at', CarbonImmutable::parse('2026-10-01'));

    insertPaymentEvent(CarbonImmutable::parse('2026-09-15 09:00:00'));
    insertPaymentEvent(CarbonImmutable::parse('2026-10-15 09:00:00'));

    $plan = collect(DB::select("EXPLAIN SELECT * FROM payment_events WHERE occurred_at >= '2026-10-01' AND occurred_at < '2026-11-01'"))
        ->pluck('QUERY PLAN')
        ->implode("\n");

    expect($plan)->toContain('payment_events_2026_10')
        ->not->toContain('payment_events_2026_09');
});
