<?php

namespace App\Modules\PaymentProviders\Support\Signature;

use App\Modules\PaymentProviders\Exceptions\ProviderConfigurationException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * 2C2P HS256 JWT encode/decode. Used for the `payload` envelope around
 * paymentToken / transactionStatus requests and callback verification.
 *
 * @see \Hakhant\Payments\Infrastructure\Gateways\TwoC2P\TwoC2PJwt
 */
class TwoC2PJwt
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function encode(array $payload, string $secret): string
    {
        try {
            return JWT::encode($payload, $secret, 'HS256');
        } catch (Throwable $throwable) {
            throw new ProviderConfigurationException(
                '2C2P JWT encode failed: '.$throwable->getMessage(),
                'TWOC2P_JWT_ENCODE',
                $throwable,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $jwt, string $secret): array
    {
        try {
            $decoded = JWT::decode($jwt, new Key($secret, 'HS256'));
        } catch (Throwable $throwable) {
            throw new ProviderConfigurationException(
                '2C2P JWT decode failed: '.$throwable->getMessage(),
                'TWOC2P_JWT_DECODE',
                $throwable,
            );
        }

        $encoded = json_encode($decoded);

        return json_decode($encoded, true) ?? [];
    }
}
