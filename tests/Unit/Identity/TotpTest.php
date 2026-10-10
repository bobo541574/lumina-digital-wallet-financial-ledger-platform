<?php

use App\Modules\Identity\Support\Totp;

// Secret is ASCII "12345678901234567890" (RFC 6238 test key), base32-encoded.
beforeEach(function () {
    $this->totp = new Totp;
    $this->secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
});

it('matches RFC 6238 SHA-1 test vectors', function (int $timestamp, string $expected) {
    expect($this->totp->code($this->secret, $timestamp))->toBe($expected);
})->with([
    [59, '287082'],
    [1111111109, '081804'],
    [1111111111, '050471'],
    [1234567890, '005924'],
    [2000000000, '279037'],
]);

it('generates a base32 secret', function () {
    $secret = $this->totp->generateSecret();

    expect($secret)->toMatch('/^[A-Z2-7]+$/')
        ->and(strlen($secret))->toBe(32);
});

it('verifies a current code within the drift window', function () {
    $code = $this->totp->code($this->secret, 1111111109);

    expect($this->totp->verify($this->secret, $code, 1, 1111111109))->toBeTrue()
        ->and($this->totp->verify($this->secret, $code, 1, 1111111109 + 30))->toBeTrue()
        ->and($this->totp->verify($this->secret, $code, 0, 1111111109 + 60))->toBeFalse();
});

it('rejects malformed codes', function () {
    expect($this->totp->verify($this->secret, 'abcdef'))->toBeFalse()
        ->and($this->totp->verify($this->secret, '12345'))->toBeFalse()
        ->and($this->totp->verify($this->secret, '12345678'))->toBeFalse();
});

it('builds an otpauth uri', function () {
    $uri = $this->totp->otpauthUri('ABC234', 'user@example.com', 'Lumina');

    expect($uri)->toStartWith('otpauth://totp/Lumina:user%40example.com?')
        ->toContain('secret=ABC234')
        ->toContain('issuer=Lumina')
        ->toContain('digits=6')
        ->toContain('period=30');
});
