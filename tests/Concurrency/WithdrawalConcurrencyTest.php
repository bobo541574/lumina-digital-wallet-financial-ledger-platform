<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Wallets\Models\WalletBalance;
use App\Modules\Withdrawals\DTOs\WithdrawalData;
use App\Modules\Withdrawals\Enums\WithdrawalStatus;
use App\Modules\Withdrawals\Models\WalletHold;
use App\Modules\Withdrawals\Models\Withdrawal;
use App\Modules\Withdrawals\Services\WithdrawalService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Support\ConcurrencyTestSupport;
use Tests\Support\WalletTestSupport;

uses(DatabaseTruncation::class, ConcurrencyTestSupport::class, WalletTestSupport::class);

beforeEach(function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for concurrency tests.');
    }

    $this->seedFinancials();
    $this->seed(RolePermissionSeeder::class);

    $this->customer = User::factory()->create();
    $this->customer->assignRole(Role::Customer->value);
    $this->wallet = $this->openWallet($this->customer, 'USD');
    $this->fundWallet($this->wallet, 10000);
});

it('never lets concurrent withdrawals reserve more than the available balance', function () {
    $workers = 8;
    $amount = 8000;

    $results = $this->runConcurrently($workers, function (int $index) use ($amount): array {
        return app(WithdrawalService::class)->request(new WithdrawalData(
            actorId: $this->customer->id,
            walletId: $this->wallet->id,
            amount: $amount,
            fee: 0,
            provider: 'fake',
            destination: ['type' => 'bank_account', 'last4' => '4242'],
            idempotencyKey: 'conc-withdrawal-'.$index,
            requestHash: hash('sha256', 'conc-withdrawal-'.$index),
        ))->body;
    });

    $succeeded = collect($results)->filter(fn (array $result) => $result['ok'])->count();
    $insufficient = collect($results)
        ->filter(fn (array $result) => ! $result['ok'] && ($result['code'] ?? null) === 'INSUFFICIENT_FUNDS')
        ->count();

    $balance = WalletBalance::find($this->wallet->id);

    expect($succeeded)->toBe(1)
        ->and($insufficient)->toBe($workers - 1)
        ->and($balance->posted_balance)->toBe(10000)
        ->and($balance->reserved_balance)->toBe($amount)
        ->and($balance->availableBalance())->toBe(2000)
        ->and(WalletHold::query()->where('status', 'active')->count())->toBe(1)
        ->and(Withdrawal::query()->where('status', WithdrawalStatus::Reserved)->count())->toBe(1);
});
