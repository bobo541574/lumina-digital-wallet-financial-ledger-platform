<?php

namespace App\Modules\PaymentProviders\Support\Signature;

/**
 * WaveMoney HMAC-SHA256 hash built by concatenating a fixed list of fields.
 *
 * @see \Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyHash
 */
class WaveMoneyHash
{
    /**
     * @param  list<string|null>  $parts
     */
    public function sign(array $parts, string $secret): string
    {
        return hash_hmac('sha256', $this->message($parts), $secret);
    }

    /**
     * @param  list<string|null>  $parts
     */
    public function verify(array $parts, string $providedHash, string $secret): bool
    {
        if ($providedHash === '') {
            return false;
        }

        return hash_equals($this->sign($parts, $secret), strtolower($providedHash));
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function message(array $parts): string
    {
        $message = '';

        foreach ($parts as $part) {
            $message .= $part === null ? 'null' : (string) $part;
        }

        return $message;
    }
}
