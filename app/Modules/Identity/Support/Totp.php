<?php

namespace App\Modules\Identity\Support;

/**
 * Minimal RFC 6238 (TOTP) / RFC 4226 (HOTP) implementation.
 *
 * SHA-1, 6 digits, 30-second period — compatible with Google Authenticator,
 * Authy, 1Password and similar. Secrets are base32 (RFC 4648, no padding).
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const DIGITS = 6;

    public const PERIOD = 30;

    /**
     * Generate a fresh base32 secret (160 bits, as recommended by RFC 4226).
     */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /**
     * The current 6-digit code for a secret.
     */
    public function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD);

        return $this->hotp($secret, $counter);
    }

    /**
     * Verify a user-supplied code, tolerating clock drift of `$window` periods
     * either side of now. Comparison is constant-time.
     */
    public function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! ctype_digit($code) || strlen($code) !== self::DIGITS) {
            return false;
        }

        $now = $timestamp ?? time();
        $matched = false;

        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidate = $this->code($secret, $now + ($offset * self::PERIOD));
            $matched = hash_equals($candidate, $code) || $matched;
        }

        return $matched;
    }

    public function otpauthUri(string $secret, string $account, ?string $issuer = null): string
    {
        $issuer = $issuer ?: (string) config('security.two_factor.issuer', config('app.name', 'Lumina'));
        $label = rawurlencode($issuer).':'.rawurlencode($account);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $label,
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    private function hotp(string $secret, int $counter): string
    {
        $binaryCounter = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $this->base32Decode($secret), true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $modulo = 10 ** self::DIGITS;

        return str_pad((string) ($value % $modulo), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $bits = '';

        foreach (str_split($data) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';

        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= self::ALPHABET[bindec($chunk)];
        }

        return $output;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(rtrim(rtrim($secret, '=')));

        $bits = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);

            if ($index === false) {
                continue;
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
