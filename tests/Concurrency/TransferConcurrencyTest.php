<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Transfers\DTOs\TransferData;
use App\Modules\Transfers\Models\Transfer;
use App\Modules\Transfers\Services\TransferService;
use App\Modules\Wallets\Models\WalletBalance;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\Support\ConcurrencyTestSupport;
use Tests\Support\WalletTestSupport;

uses(DatabaseTruncation::class, ConcurrencyTestSupport::class, WalletTestSupport::class);

beforeEach(function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for concurrency tests.');
    }

    $this->seedFinancials();
    $this->seed(RolePermissionSeeder::class);

    $this->sender = User::factory()->create();
    $this->sender->assignRole(Role::Customer->value);
    $this->senderWallet = $this->openWallet($this->sender, 'USD');
    $this->fundWallet($this->senderWallet, 10000);

    $this->recipient = User::factory()->create();
    $this->recipient->assignRole(Role::Customer->value);
    $this->recipientWallet = $this->openWallet($this->recipient, 'USD');
});

it('never allows concurrent transfers to overspend the sender', function () {
    $workers = 8;
    $amount = 6000;

    $results = $this->runConcurrently($workers, function (int $index) use ($amount): array {
        return app(TransferService::class)->transfer(new TransferData(
            actorId: $this->sender->id,
            senderWalletId: $this->senderWallet->id,
            recipientWalletId: $this->recipientWallet->id,
            currencyCode: 'USD',
            amount: $amount,
            fee: 0,
            idempotencyKey: 'conc-transfer-'.$index,
            requestHash: hash('sha256', 'conc-transfer-'.$index),
        ))->body;
    });

    $succeeded = collect($results)->filter(fn (array $result) => $result['ok'])->count();
    $insufficient = collect($results)
        ->filter(fn (array $result) => ! $result['ok'] && ($result['code'] ?? null) === 'INSUFFICIENT_FUNDS')
        ->count();

    $sender = WalletBalance::find($this->senderWallet->id);
    $recipient = WalletBalance::find($this->recipientWallet->id);

    expect($succeeded)->toBe(1)
        ->and($insufficient)->toBe($workers - 1)
        ->and($sender->posted_balance)->toBe(4000)
        ->and($sender->reserved_balance)->toBe(0)
        ->and($recipient->posted_balance)->toBe($amount)
        ->and(Transfer::query()->where('status', 'succeeded')->count())->toBe(1)
        ->and(Transfer::query()->where('status', 'succeeded')->count() * $amount)->toBe(6000);
});

it('keeps the ledger balanced after a concurrent transfer race', function () {
    $workers = 6;

    $this->runConcurrently($workers, function (int $index): array {
        return app(TransferService::class)->transfer(new TransferData(
            actorId: $this->sender->id,
            senderWalletId: $this->senderWallet->id,
            recipientWalletId: $this->recipientWallet->id,
            currencyCode: 'USD',
            amount: 4000,
            fee: 0,
            idempotencyKey: 'conc-balance-'.$index,
            requestHash: hash('sha256', 'conc-balance-'.$index),
        ))->body;
    });

    $debits = (int) DB::table('ledger_postings')->where('direction', 'debit')->sum('amount');
    $credits = (int) DB::table('ledger_postings')->where('direction', 'credit')->sum('amount');

    expect($debits)->toBe($credits);
});
