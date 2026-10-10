<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Exceptions\InvalidTwoFactorCodeException;
use App\Modules\Identity\Exceptions\TwoFactorAlreadyEnabledException;
use App\Modules\Identity\Exceptions\TwoFactorNotEnabledException;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\Totp;
use Illuminate\Support\Str;

/**
 * TOTP-based two-factor authentication: enrolment, confirmation, recovery
 * codes and per-login verification.
 *
 * Secrets and (hashed) recovery codes are encrypted at rest by the User model
 * casts and never leave the server except as the one-time enrolment payload.
 */
class TwoFactorService
{
    public function __construct(private readonly Totp $totp) {}

    /**
     * Begin enrolment: generate and persist a pending secret.
     *
     * @return array{secret: string, otpauth_uri: string}
     */
    public function begin(User $user): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new TwoFactorAlreadyEnabledException;
        }

        $secret = $this->totp->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'otpauth_uri' => $this->totp->otpauthUri($secret, $user->email),
        ];
    }

    /**
     * Confirm enrolment with a valid code and issue one-time recovery codes.
     *
     * @return array<int, string> plaintext recovery codes, shown only once
     */
    public function confirm(User $user, string $code): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new TwoFactorAlreadyEnabledException;
        }

        if (blank($user->two_factor_secret)) {
            throw new TwoFactorNotEnabledException;
        }

        if (! $this->totp->verify((string) $user->two_factor_secret, $code, $this->window())) {
            throw new InvalidTwoFactorCodeException;
        }

        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $this->hashRecoveryCodes($codes),
        ])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Issue a fresh set of recovery codes, invalidating any previous ones.
     *
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        if (! $user->hasTwoFactorEnabled()) {
            throw new TwoFactorNotEnabledException;
        }

        $codes = $this->generateRecoveryCodes();

        $user->forceFill(['two_factor_recovery_codes' => $this->hashRecoveryCodes($codes)])->save();

        return $codes;
    }

    /**
     * Verify a second factor. Accepts a TOTP code or a single-use recovery
     * code (which is consumed on success).
     */
    public function verify(User $user, string $code): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return true;
        }

        if ($this->totp->verify((string) $user->two_factor_secret, $code, $this->window())) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        /** @var array<int, string> $hashes */
        $hashes = $user->two_factor_recovery_codes ?? [];
        $needle = hash('sha256', $code);

        $matched = false;
        $remaining = [];

        foreach ($hashes as $hash) {
            if (! $matched && hash_equals($hash, $needle)) {
                $matched = true;

                continue;
            }

            $remaining[] = $hash;
        }

        if ($matched) {
            $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
        }

        return $matched;
    }

    /**
     * @return array<int, string>
     */
    private function generateRecoveryCodes(): array
    {
        $count = max(1, (int) config('security.two_factor.recovery_codes', 8));
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random(10)).'-'.Str::upper(Str::random(10));
        }

        return $codes;
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    private function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => hash('sha256', $code), $codes);
    }

    private function window(): int
    {
        return max(0, (int) config('security.two_factor.window', 1));
    }
}
