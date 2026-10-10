<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\PaymentProviders\Support\Signature\KbzPaySignature;
use App\Modules\PaymentProviders\Support\Signature\TwoC2PJwt;
use App\Modules\PaymentProviders\Support\Signature\WaveMoneyHash;
use App\Modules\Wallets\Models\WalletBalance;
use Illuminate\Support\Facades\Http;
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

    Sanctum::actingAs($this->customer);
});

function providerDeposit(string $provider, int $amount = 2500, string $key = 'provider-key-1'): array
{
    return test()->withHeaders(['Idempotency-Key' => $key])
        ->postJson('/api/v1/deposits/provider', [
            'wallet_id' => test()->wallet->id,
            'amount' => $amount,
            'provider' => $provider,
        ])->json('data');
}

function postWebhook(string $provider, array $payload): TestResponse
{
    return test()->call(
        'POST',
        "/api/v1/payment-providers/{$provider}/webhook",
        [], [], [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode($payload),
    );
}

it('opens a kbzpay collect intent and settles it through a signed webhook', function () {
    config([
        'payment.providers.kbzpay.app_id' => 'app-1',
        'payment.providers.kbzpay.merchant_code' => 'M001',
        'payment.providers.kbzpay.secret' => 'kbz-secret',
        'payment.providers.kbzpay.pay_type' => 'pwa',
    ]);

    Http::fake([
        'api.kbzpay.com/*' => Http::response(['Response' => ['result' => 'SUCCESS', 'prepay_id' => 'PP-1']]),
    ]);

    $intent = providerDeposit('kbzpay', 2500, 'kbz-key-1');

    expect($intent['status'])->toBe('awaiting_payment')
        ->and($intent['provider_transaction_id'])->toBe('PP-1')
        ->and($intent['payment_url'])->toContain('PP-1');

    $fields = [
        'appid' => 'app-1',
        'merch_code' => 'M001',
        'merch_order_id' => $intent['provider_reference'],
        'total_amount' => '2500',
        'trans_currency' => 'USD',
        'trade_status' => 'SUCCESS',
    ];
    $fields['sign'] = (new KbzPaySignature)->sign($fields, 'kbz-secret');

    postWebhook('kbzpay', $fields)->assertOk()->assertJsonPath('data.status', 'succeeded');

    expect(WalletBalance::find($this->wallet->id)->posted_balance)->toBe(2500);
});

it('opens a wavemoney collect intent and settles it through a signed webhook', function () {
    config([
        'payment.providers.wavemoney.merchant_id' => 'WM-1',
        'payment.providers.wavemoney.secret_key' => 'wm-secret',
    ]);

    Http::fake([
        'testpayments.wavemoney.io:8107/*' => Http::response(['message' => 'success', 'transaction_id' => 'TXN-1']),
    ]);

    $intent = providerDeposit('wavemoney', 2500, 'wm-key-1');

    expect($intent['status'])->toBe('awaiting_payment')
        ->and($intent['provider_transaction_id'])->toBe('TXN-1')
        ->and($intent['payment_url'])->toContain('TXN-1');

    $payload = [
        'status' => 'PAYMENT_CONFIRMED',
        'timeToLiveSeconds' => '60',
        'merchantId' => 'WM-1',
        'orderId' => 'ORDER-1',
        'amount' => '2500',
        'backendResultUrl' => 'https://example.com/backend',
        'merchantReferenceId' => $intent['provider_reference'],
        'initiatorMsisdn' => '09900000000',
        'transactionId' => 'TXN-1',
        'paymentRequestId' => 'PR-1',
        'requestTime' => '2026-01-01 00:00:00',
        'currency' => 'USD',
    ];
    $payload['hashValue'] = (new WaveMoneyHash)->sign([
        $payload['status'], $payload['timeToLiveSeconds'], $payload['merchantId'],
        $payload['orderId'], $payload['amount'], $payload['backendResultUrl'],
        $payload['merchantReferenceId'], $payload['initiatorMsisdn'],
        $payload['transactionId'], $payload['paymentRequestId'], $payload['requestTime'],
    ], 'wm-secret');

    postWebhook('wavemoney', $payload)->assertOk()->assertJsonPath('data.status', 'succeeded');

    expect(WalletBalance::find($this->wallet->id)->posted_balance)->toBe(2500);
});

it('opens a 2c2p collect intent and settles it through a signed webhook', function () {
    $secret = str_repeat('c', 32);

    config([
        'payment.providers.2c2p.merchant_id' => 'M2C2P',
        'payment.providers.2c2p.secret_key' => $secret,
    ]);

    $jwt = new TwoC2PJwt;

    Http::fake([
        'sandbox-pgw.2c2p.com/*' => Http::response([
            'payload' => $jwt->encode([
                'respCode' => '0000',
                'paymentToken' => 'PT-1',
                'webPaymentUrl' => 'https://sandbox-pgw.2c2p.com/pay/PT-1',
            ], $secret),
        ]),
    ]);

    $intent = providerDeposit('2c2p', 2500, 'c2p-key-1');

    expect($intent['status'])->toBe('awaiting_payment')
        ->and($intent['provider_transaction_id'])->toBe('PT-1')
        ->and($intent['payment_url'])->toContain('PT-1');

    $body = ['payload' => $jwt->encode([
        'merchantID' => 'M2C2P',
        'invoiceNo' => $intent['provider_reference'],
        'paymentID' => 'PAY-1',
        'amount' => 2500,
        'currencyCode' => 'USD',
        'paymentResultDetails' => ['code' => '00'],
    ], $secret)];

    postWebhook('2c2p', $body)->assertOk()->assertJsonPath('data.status', 'succeeded');

    expect(WalletBalance::find($this->wallet->id)->posted_balance)->toBe(2500);
});

it('opens an aya qr collect intent', function () {
    config([
        'payment.providers.aya.basic_token' => 'basic',
        'payment.providers.aya.phone' => '09123456789',
        'payment.providers.aya.password' => 'pw',
        'payment.providers.aya.service_code' => null,
        'payment.providers.aya.encrypt_key' => null,
    ]);

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'login')) {
            return Http::response(['token' => ['token' => 'BR-1']]);
        }

        return Http::response(['data' => ['externalTransactionId' => 'GEN', 'status' => 0]]);
    });

    $intent = providerDeposit('aya', 2500, 'aya-key-1');

    expect($intent['status'])->toBe('awaiting_payment')
        ->and($intent['provider'])->toBe('aya');
});

it('rejects an unknown provider', function () {
    $this->withHeaders(['Idempotency-Key' => 'bad-key-1'])
        ->postJson('/api/v1/deposits/provider', [
            'wallet_id' => $this->wallet->id,
            'amount' => 2500,
            'provider' => 'not-real',
        ])->assertStatus(422);
});
