<?php

use App\Modules\PaymentProviders\Exceptions\ProviderConfigurationException;
use App\Modules\PaymentProviders\Support\Signature\AyaCrypto;
use App\Modules\PaymentProviders\Support\Signature\KbzPaySignature;
use App\Modules\PaymentProviders\Support\Signature\TwoC2PJwt;
use App\Modules\PaymentProviders\Support\Signature\WaveMoneyHash;

it('signs and verifies a kbzpay parameter set, ignoring sign/sign_type/arrays', function () {
    $signer = new KbzPaySignature;

    $fields = [
        'appid' => 'app-1',
        'merch_code' => 'M001',
        'total_amount' => '2500',
        'sign_type' => 'SHA256',
        'biz_content' => ['nested' => 'ignored'],
    ];

    $signature = $signer->sign($fields, 'secret');

    expect($signature)->toBe(strtoupper($signature))
        ->and($signer->verify($fields + ['sign' => $signature], $signature, 'secret'))->toBeTrue()
        ->and($signer->verify($fields + ['sign' => $signature], 'DEADBEEF', 'secret'))->toBeFalse();
});

it('builds a wavemoney hash over the fixed field order treating null as literal null', function () {
    $hash = new WaveMoneyHash;

    $signature = $hash->sign(['1', 'M1', 'ORDER', '2500', null, 'REF'], 'secret');

    expect($signature)->toBe(hash_hmac('sha256', '1M1ORDER2500nullREF', 'secret'))
        ->and($hash->verify(['1', 'M1', 'ORDER', '2500', null, 'REF'], $signature, 'secret'))->toBeTrue()
        ->and($hash->verify(['1', 'M1', 'ORDER', '2500', null, 'REF'], strtoupper($signature), 'secret'))->toBeTrue()
        ->and($hash->verify(['1', 'M1', 'ORDER', '2500', null, 'REF'], '', 'secret'))->toBeFalse();
});

it('round-trips a 2c2p jwt payload', function () {
    $jwt = new TwoC2PJwt;

    $secret = str_repeat('c', 32);

    $token = $jwt->encode(['merchantID' => 'M2C2P', 'amount' => '100'], $secret);

    expect($jwt->decode($token, $secret))->toMatchArray(['merchantID' => 'M2C2P', 'amount' => '100']);
});

it('rejects a 2c2p jwt signed with the wrong secret', function () {
    $jwt = new TwoC2PJwt;

    $token = $jwt->encode(['merchantID' => 'M2C2P'], str_repeat('c', 32));

    expect(fn () => $jwt->decode($token, str_repeat('x', 32)))->toThrow(ProviderConfigurationException::class);
});

it('encrypts and decrypts an aya paymentResult with aes-256-ecb', function () {
    $crypto = new AyaCrypto;

    $key = str_repeat('k', 32);

    $ciphertext = $crypto->encrypt('{"status":"success"}', $key);

    expect($crypto->decrypt($ciphertext, $key))->toBe('{"status":"success"}');
});

it('refuses to decrypt an aya paymentResult without a key', function () {
    $crypto = new AyaCrypto;

    expect(fn () => $crypto->decrypt('anything', ''))->toThrow(ProviderConfigurationException::class);
});
