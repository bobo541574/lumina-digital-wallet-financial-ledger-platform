<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Wallets\Models\WalletBalance;
use App\Modules\Withdrawals\Enums\HoldStatus;
use App\Modules\Withdrawals\Models\WalletHold;
use App\Modules\Withdrawals\Models\Withdrawal;
use App\Modules\Withdrawals\Services\WithdrawalService;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\WalletTestSupport;

uses(WalletTestSupport::class);

beforeEach(function () {
    $this->seedFinancials();
    $this->seed(RolePermissionSeeder::class);

    $this->customer = User::factory()->create();
    $this->customer->assignRole(Role::Customer->value);
    $this->wallet = $this->openWallet($this->customer, 'USD');
    $this->fundWallet($this->wallet, 10000);

    $this->finance = User::factory()->create();
    $this->finance->assignRole(Role::FinanceOperator->value);
});

function createWithdrawal(int $amount = 3000, int $fee = 0, string $key = 'withdraw-key-0001'): TestResponse
{
    return test()->withHeaders(['Idempotency-Key' => $key])->postJson('/api/v1/withdrawals', [
        'wallet_id' => test()->wallet->id,
        'amount' => $amount,
        'fee' => $fee,
        'provider' => 'fake',
        'destination' => ['type' => 'bank_account', 'last4' => '4242'],
    ]);
}

function signedPayoutWebhook(array $payload, ?string $signature = null): TestResponse
{
    $body = json_encode($payload);
    $signature ??= hash_hmac('sha256', $body, config('payment.providers.fake.secret'));

    return test()->call(
        'POST',
        '/api/v1/payment-providers/fake/payout-webhook',
        [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $signature],
        $body,
    );
}

it('reserves funds immediately when a withdrawal is requested', function () {
    Sanctum::actingAs($this->customer);

    createWithdrawal(amount: 3000, fee: 100)
        ->assertCreated()
        ->assertJsonPath('data.status', 'reserved');

    $balance = WalletBalance::find($this->wallet->id);
    expect($balance->posted_balance)->toBe(10000)
        ->and($balance->reserved_balance)->toBe(3100);

    $hold = WalletHold::where('wallet_id', $this->wallet->id)->first();
    expect($hold->status)->toBe(HoldStatus::Active)
        ->and($hold->amount)->toBe(3100);
});

it('rejects a withdrawal exceeding available balance', function () {
    Sanctum::actingAs($this->customer);

    createWithdrawal(amount: 9000, fee: 2000)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INSUFFICIENT_FUNDS');
});

it('is idempotent on replay', function () {
    Sanctum::actingAs($this->customer);

    $first = createWithdrawal(key: 'dup-withdraw')->json('data.id');
    $second = createWithdrawal(key: 'dup-withdraw')->json('data.id');

    expect($second)->toBe($first)
        ->and(Withdrawal::count())->toBe(1);
});

it('does not release funds when the payout outcome is unknown', function () {
    Sanctum::actingAs($this->customer);
    $withdrawal = Withdrawal::find(createWithdrawal()->json('data.id'));

    app(WithdrawalService::class)->markReconciling($withdrawal);

    $balance = WalletBalance::find($this->wallet->id);
    expect($withdrawal->fresh()->status->value)->toBe('reconciling')
        ->and($balance->reserved_balance)->toBe(3000);
});

it('settles a successful payout through the webhook and consumes the hold', function () {
    Sanctum::actingAs($this->customer);
    $withdrawal = Withdrawal::find(createWithdrawal(amount: 3000, fee: 100)->json('data.id'));

    Sanctum::actingAs($this->finance);
    $this->postJson("/api/v1/withdrawals/{$withdrawal->id}/initiate")
        ->assertOk()
        ->assertJsonPath('data.status', 'processing');

    $reference = $withdrawal->fresh()->provider_reference;

    signedPayoutWebhook([
        'id' => 'po_evt_1',
        'type' => 'payout',
        'reference' => $reference,
        'transaction_id' => 'po_txn_1',
        'status' => 'succeeded',
    ])->assertOk()->assertJsonPath('data.status', 'succeeded');

    $balance = WalletBalance::find($this->wallet->id);
    expect($balance->posted_balance)->toBe(6900)
        ->and($balance->reserved_balance)->toBe(0)
        ->and(WalletHold::where('wallet_id', $this->wallet->id)->first()->status)->toBe(HoldStatus::Consumed);
});

it('does not double post on duplicate success webhooks', function () {
    Sanctum::actingAs($this->customer);
    $withdrawal = Withdrawal::find(createWithdrawal(amount: 3000)->json('data.id'));

    Sanctum::actingAs($this->finance);
    $this->postJson("/api/v1/withdrawals/{$withdrawal->id}/initiate")->assertOk();
    $reference = $withdrawal->fresh()->provider_reference;

    $payload = [
        'id' => 'po_evt_dup',
        'type' => 'payout',
        'reference' => $reference,
        'transaction_id' => 'po_txn_dup',
        'status' => 'succeeded',
    ];

    signedPayoutWebhook($payload)->assertOk();
    signedPayoutWebhook($payload)->assertOk();

    expect(WalletBalance::find($this->wallet->id)->posted_balance)->toBe(7000);
});

it('releases the hold when the payout fails', function () {
    Sanctum::actingAs($this->customer);
    $withdrawal = Withdrawal::find(createWithdrawal(amount: 3000, fee: 100)->json('data.id'));

    Sanctum::actingAs($this->finance);
    $this->postJson("/api/v1/withdrawals/{$withdrawal->id}/initiate")->assertOk();
    $reference = $withdrawal->fresh()->provider_reference;

    signedPayoutWebhook([
        'id' => 'po_evt_fail',
        'type' => 'payout',
        'reference' => $reference,
        'transaction_id' => 'po_txn_fail',
        'status' => 'failed',
        'failure_code' => 'BANK_REJECTED',
    ])->assertOk()->assertJsonPath('data.status', 'failed');

    $balance = WalletBalance::find($this->wallet->id);
    expect($balance->posted_balance)->toBe(10000)
        ->and($balance->reserved_balance)->toBe(0)
        ->and(WalletHold::where('wallet_id', $this->wallet->id)->first()->status)->toBe(HoldStatus::Released);
});

it('lets the owner cancel a reserved withdrawal and frees the funds', function () {
    Sanctum::actingAs($this->customer);
    $withdrawal = Withdrawal::find(createWithdrawal(amount: 3000)->json('data.id'));

    $this->postJson("/api/v1/withdrawals/{$withdrawal->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect(WalletBalance::find($this->wallet->id)->reserved_balance)->toBe(0);
});
