<?php

namespace App\Modules\PaymentProviders\Support\Signature;

use App\Modules\PaymentProviders\Exceptions\ProviderConfigurationException;

/**
 * AYA Pay's `paymentResult` field is AES-256-ECB encrypted (base64) and must be
 * decrypted with the merchant's `encrypt_key` before the status can be read.
 *
 * @see https://github.com/hakhant21/myanmar-payments
 */
class AyaCrypto
{
    private const CIPHER = 'aes-256-ecb';

    public function decrypt(string $ciphertext, string $key): string
    {
        if ($key === '') {
            throw new ProviderConfigurationException('AYA encrypt_key is required to decrypt paymentResult.', 'AYA_ENCRYPT_KEY_MISSING');
        }

        $plain = openssl_decrypt(base64_decode($ciphertext, true), self::CIPHER, $key, OPENSSL_RAW_DATA);

        if ($plain === false) {
            throw new ProviderConfigurationException('AYA paymentResult could not be decrypted.', 'AYA_DECRYPT_FAILED');
        }

        return $plain;
    }

    public function encrypt(string $plaintext, string $key): string
    {
        if ($key === '') {
            throw new ProviderConfigurationException('AYA encrypt_key is required to encrypt paymentResult.', 'AYA_ENCRYPT_KEY_MISSING');
        }

        return base64_encode(openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA));
    }
}
