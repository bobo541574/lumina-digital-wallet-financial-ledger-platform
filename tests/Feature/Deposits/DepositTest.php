<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\PaymentProviders\Models\WebhookInbox;
use App\Modules\Wallets\Models\WalletBalance;
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

    $this->finance = User::factory()->create();
    $this->finance->assignRole(Role::FinanceOperator->value);
});

function createManualDeposit(int $amount = 5000, string $key = 'deposit-key-0001'): TestResponse
{
    return test()->withHeaders(['Idempotency-Key' => $key])->postJson('/api/v1/deposits', [
        'wallet_id' => test()->wallet->id,
        'amount' => $amount,
    ]);
}

function signedWebhook(array $payload, ?string $signature = null): TestResponse
{
    $body = json_encode($payload);
    $signature ??= hash_hmac('sha256', $body, config('payment.providers.fake.secret'));

    return test()->call(
        'POST',
        '/api/v1/payment-providers/fake/webhook',
        [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $signature],
        $body,
    );
}

it('creates a manual deposit awaiting approval without crediting', function () {
    Sanctum::actingAs($this->customer);

    createManualDeposit()->assertCreated()->assertJsonPath('data.status', 'awaiting_approval');

    expect(WalletBalance::find($this->wallet->id)->posted_balance)->toBe(0);
});

it('does not credit on approval, only on confirmation', function () {
    Sanctum::actingAs($this->customer);
    $depositId = createManualDeposit()->json('data.id');

    Sanctum::actingAs($this->finance);

    $this->postJson("/api/v1/deposits/{$depositId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect(WalletBalance::find($this->wallet->id)->posted_balance)->toBe(0);

    $this->postJson("/api/v1/deposits/{$depositId}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status', 'succeeded');

    expect(WalletBalance::find($this->wallet->id)->posted_balance)->toBe(5000);
});

it('blocks self approval of a deposit', function () {
    $operator = User::factory()->create();
    $operator->assignRole(Role::Customer->value);
    $operator->assignRole(Role::FinanceOperator->value);
    $wallet = $this->openWallet($operator, 'USD');

    Sanctum::actingAs($operator);
    $depositId = $this->withHeaders(['Idempotency-Key' => 'self-approve-01'])
        ->postJson('/api/v1/deposits', ['wallet_id' => $wallet->id, 'amount' => 1000])
        ->json('data.id');

    $this->postJson("/api/v1/deposits/{$depositId}/approve")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'DEPOSIT_SELF_APPROVAL');
});

it('credits a provider deposit once via signed webhook', function () {
    Sanctum::actingAs($this->customer);

    $intent = $this->withHeaders(['Idempotency-Key' => 'provider-intent-1'])
        ->postJson('/api/v1/deposits/provider', [
            'wallet_id' => $this->wallet->id,
            'amount' => 2500,
            'provider' => 'fake',
        ])
        ->assertCreated()
        ->json('data');

    signedWebhook([
        'id' => 'evt_1',
        'type' => 'payment.succeeded',
        'reference' => $intent['provider_reference'],
        'transaction_id' => 'txn_1',
        'amount' => 2500,
        'currency' => 'USD',
        'status' => 'succeeded',
    ])->assertOk()->assertJsonPath('data.status', 'succeeded');

    expect(WalletBalance::find($this->wallet->id)->posted_balance)->toBe(2500);
});

it('acknowledges duplicate webhooks without double crediting', function () {
    Sanctum::actingAs($this->customer);
    $intent = $this->withHeaders(['Idempotency-Key' => 'provider-intent-2'])
        ->postJson('/api/v1/deposits/provider', [
            'wallet_id' => $this->wallet->id,
            'amount' => 2500,
            'provider' => 'fake',
        ])->json('data');

    $payload = [
        'id' => 'evt_dup',
        'type' => 'payment.succeeded',
        'reference' => $intent['provider_reference'],
        'transaction_id' => 'txn_dup',
        'amount' => 2500,
        'currency' => 'USD',
        'status' => 'succeeded',
    ];

    signedWebhook($payload)->assertOk();
    signedWebhook($payload)->assertOk()->assertJsonPath('error.code', 'WEBHOOK_DUPLICATE');

    expect(WalletBalance::find($this->wallet->id)->posted_balance)->toBe(2500)
        ->and(WebhookInbox::where('provider', 'fake')->where('event_id', 'evt_dup')->count())->toBe(1);
});

it('rejects an invalid webhook signature without side effects', function () {
    Sanctum::actingAs($this->customer);
    $intent = $this->withHeaders(['Idempotency-Key' => 'provider-intent-3'])
        ->postJson('/api/v1/deposits/provider', [
            'wallet_id' => $this->wallet->id,
            'amount' => 2500,
            'provider' => 'fake',
        ])->json('data');

    signedWebhook([
        'id' => 'evt_bad',
        'type' => 'payment.succeeded',
        'reference' => $intent['provider_reference'],
        'transaction_id' => 'txn_bad',
        'amount' => 2500,
        'currency' => 'USD',
        'status' => 'succeeded',
    ], signature: 'deadbeef')->assertStatus(400)->assertJsonPath('error.code', 'WEBHOOK_SIGNATURE_INVALID');

    expect(WalletBalance::find($this->wallet->id)->posted_balance)->toBe(0);
});

it('quarantines a webhook whose amount does not match the intent', function () {
    Sanctum::actingAs($this->customer);
    $intent = $this->withHeaders(['Idempotency-Key' => 'provider-intent-4'])
        ->postJson('/api/v1/deposits/provider', [
            'wallet_id' => $this->wallet->id,
            'amount' => 2500,
            'provider' => 'fake',
        ])->json('data');

    signedWebhook([
        'id' => 'evt_mismatch',
        'type' => 'payment.succeeded',
        'reference' => $intent['provider_reference'],
        'transaction_id' => 'txn_mismatch',
        'amount' => 9999,
        'currency' => 'USD',
        'status' => 'succeeded',
    ])->assertStatus(202)->assertJsonPath('error.code', 'WEBHOOK_QUARANTINED');

    expect(WalletBalance::find($this->wallet->id)->posted_balance)->toBe(0);

    $this->assertDatabaseHas('deposits', ['id' => $intent['id'], 'status' => 'reconciling']);
});
