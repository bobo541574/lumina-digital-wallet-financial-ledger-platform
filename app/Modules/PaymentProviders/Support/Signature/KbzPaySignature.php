<?php

namespace App\Modules\PaymentProviders\Support\Signature;

/**
 * KBZPay SHA256 request/callback signing.
 *
 * Spec: sort every non-empty scalar parameter (excluding `sign` and
 * `sign_type`, and excluding JSON array values) by ASCII key, join as
 * `key=value` with `&`, append `&key={secret}`, then SHA256 uppercase.
 *
 * @see \Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPaySignature
 */
class KbzPaySignature
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function sign(array $fields, string $secret): string
    {
        return strtoupper(hash('sha256', $this->signingBase($fields).'&key='.$secret));
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function verify(array $fields, string $providedSignature, string $secret): bool
    {
        return hash_equals($this->sign($fields, $secret), strtoupper($providedSignature));
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function signingBase(array $fields): string
    {
        $normalized = [];

        foreach ($fields as $key => $value) {
            if ($key === 'sign' || $key === 'sign_type' || is_array($value)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $normalized[$key] = (string) $value;
        }

        ksort($normalized, SORT_STRING);

        $pairs = [];
        foreach ($normalized as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        return implode('&', $pairs);
    }
}
