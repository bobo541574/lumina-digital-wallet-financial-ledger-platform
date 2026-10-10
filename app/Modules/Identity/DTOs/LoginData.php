<?php

namespace App\Modules\Identity\DTOs;

final readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName = 'api',
        public ?string $twoFactorCode = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            email: $validated['email'],
            password: $validated['password'],
            deviceName: $validated['device_name'] ?? 'api',
            twoFactorCode: $validated['two_factor_code'] ?? null,
        );
    }
}
