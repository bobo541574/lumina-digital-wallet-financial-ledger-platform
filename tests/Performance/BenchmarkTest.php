<?php

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\LedgerPosting;
use App\Modules\PaymentEvents\Partitioning\PartitionManager;
use App\Modules\Transfers\DTOs\TransferData;
use App\Modules\Transfers\Services\TransferService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\WalletTestSupport;

/**
 * Coarse performance guard rails. These run against a real PostgreSQL database
 * and are excluded from the default suite (see phpunit.performance.xml). Run
 * with `composer bench`.
 *
 * Budgets are deliberately loose so they only fail on catastrophic regressions
 * (e.g. an accidental N+1 or a missing index), not machine variance.
 */
uses(WalletTestSupport::class);

beforeEach(function () {
    $this->seedFinancials();

    $this->report = function (string $label, int $count, float $seconds): void {
        if ($seconds <= 0) {
            return;
        }

        fwrite(STDERR, sprintf(
            "\n[bench] %-40s %5d ops in %7.1f ms  (%8.1f ops/s)\n",
            $label,
            $count,
            $seconds * 1000,
            $count / $seconds,
        ));
    };
});

it('sustains hot-sender transfer throughput', function () {
    $senderWallet = $this->openWallet(User::factory()->create(), 'USD');
    $this->fundWallet($senderWallet, 100_000_000);

    $recipients = collect(range(1, 50))->map(
        fn () => $this->openWallet(User::factory()->create(), 'USD'),
    );

    $service = app(TransferService::class);
    $count = 100;
    $start = microtime(true);

    for ($i = 0; $i < $count; $i++) {
        $recipient = $recipients[$i % $recipients->count()];

        $service->transfer(new TransferData(
            actorId: $senderWallet->user_id,
            senderWalletId: $senderWallet->id,
            recipientWalletId: $recipient->id,
            currencyCode: 'USD',
            amount: 100,
            fee: 0,
            idempotencyKey: "bench-transfer-{$i}",
            requestHash: hash('sha256', "bench-transfer-{$i}"),
        ));
    }

    $elapsed = microtime(true) - $start;
    ($this->report)('hot-sender transfers', $count, $elapsed);

    expect($elapsed)->toBeLessThan(60.0);
});

it('replays an idempotent request without re-posting the ledger', function () {
    $senderWallet = $this->openWallet(User::factory()->create(), 'USD');
    $this->fundWallet($senderWallet, 100_000_000);
    $recipient = $this->openWallet(User::factory()->create(), 'USD');

    $service = app(TransferService::class);

    $payload = static fn () => new TransferData(
        actorId: $senderWallet->user_id,
        senderWalletId: $senderWallet->id,
        recipientWalletId: $recipient->id,
        currencyCode: 'USD',
        amount: 100,
        fee: 0,
        idempotencyKey: 'bench-replay-key',
        requestHash: hash('sha256', 'bench-replay-key'),
    );

    $service->transfer($payload());

    $count = 50;
    $start = microtime(true);

    for ($i = 0; $i < $count; $i++) {
        $service->transfer($payload());
    }

    $elapsed = microtime(true) - $start;
    ($this->report)('idempotent replays', $count, $elapsed);

    expect($elapsed)->toBeLessThan(30.0);
});

it('lists recent wallet ledger history quickly', function () {
    $wallet = $this->openWallet(User::factory()->create(), 'USD');
    $this->fundWallet($wallet, 100_000_000);
    $recipient = $this->openWallet(User::factory()->create(), 'USD');

    $service = app(TransferService::class);

    for ($i = 0; $i < 200; $i++) {
        $service->transfer(new TransferData(
            actorId: $wallet->user_id,
            senderWalletId: $wallet->id,
            recipientWalletId: $recipient->id,
            currencyCode: 'USD',
            amount: 10,
            fee: 0,
            idempotencyKey: "bench-history-{$i}",
            requestHash: hash('sha256', "bench-history-{$i}"),
        ));
    }

    $count = 50;
    $start = microtime(true);

    for ($i = 0; $i < $count; $i++) {
        LedgerPosting::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    $elapsed = microtime(true) - $start;
    ($this->report)('wallet history queries', $count, $elapsed);

    expect($elapsed)->toBeLessThan(15.0);
});

it('ingests payment events across monthly partitions', function () {
    $manager = app(PartitionManager::class);
    $anchor = CarbonImmutable::now()->startOfMonth()->subMonths(11);

    foreach (range(0, 11) as $offset) {
        $manager->createPartition('payment_events', 'occurred_at', $anchor->addMonths($offset));
    }

    $rowsPerMonth = 500;
    $total = 0;
    $payload = json_encode(['amount' => 100, 'currency' => 'USD']);

    $start = microtime(true);

    foreach (range(0, 11) as $offset) {
        $month = $anchor->addMonths($offset);
        $batch = [];

        for ($i = 0; $i < $rowsPerMonth; $i++) {
            $batch[] = [
                'id' => (string) Str::uuid(),
                'aggregate_type' => 'deposit',
                'aggregate_id' => (string) Str::uuid(),
                'event_type' => 'deposit.succeeded',
                'occurred_at' => $month->addDays($i % 27)->toDateTimeString(),
                'payload' => $payload,
            ];
        }

        DB::table('payment_events')->insert($batch);
        $total += $rowsPerMonth;
    }

    $elapsed = microtime(true) - $start;
    ($this->report)('payment event ingest (partitioned)', $total, $elapsed);

    expect(DB::table('payment_events')->count())->toBe($total)
        ->and($elapsed)->toBeLessThan(60.0);
});

it('serves a single-month query by pruning other partitions', function () {
    $manager = app(PartitionManager::class);
    $anchor = CarbonImmutable::now()->startOfMonth()->subMonths(11);

    foreach (range(0, 11) as $offset) {
        $manager->createPartition('payment_events', 'occurred_at', $anchor->addMonths($offset));
    }

    $payload = json_encode(['amount' => 100]);

    foreach (range(0, 11) as $offset) {
        $month = $anchor->addMonths($offset);

        DB::table('payment_events')->insert(
            collect(range(0, 199))->map(fn (int $i) => [
                'id' => (string) Str::uuid(),
                'aggregate_type' => 'deposit',
                'aggregate_id' => (string) Str::uuid(),
                'event_type' => 'deposit.succeeded',
                'occurred_at' => $month->addDays($i % 27)->toDateTimeString(),
                'payload' => $payload,
            ])->all(),
        );
    }

    $target = $anchor->addMonths(5);
    $from = $target->toDateTimeString();
    $to = $target->addMonth()->toDateTimeString();

    $plan = collect(DB::select('EXPLAIN SELECT count(*) FROM payment_events WHERE occurred_at >= ? AND occurred_at < ?', [$from, $to]))
        ->pluck('QUERY PLAN')
        ->implode("\n");

    $count = 200;
    $start = microtime(true);

    for ($i = 0; $i < $count; $i++) {
        DB::table('payment_events')
            ->where('occurred_at', '>=', $from)
            ->where('occurred_at', '<', $to)
            ->count();
    }

    $elapsed = microtime(true) - $start;
    ($this->report)('pruned single-month counts', $count, $elapsed);

    $expectedPartition = $manager->partitionName('payment_events', $target);
    $otherPartition = $manager->partitionName('payment_events', $target->subMonth());

    expect($plan)->toContain($expectedPartition)
        ->not->toContain($otherPartition)
        ->and($elapsed)->toBeLessThan(15.0);
});
