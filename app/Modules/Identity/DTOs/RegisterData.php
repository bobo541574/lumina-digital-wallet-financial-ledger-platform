<?php

namespace App\Modules\Identity\DTOs;

final readonly class RegisterData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $deviceName = 'api',
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            deviceName: $validated['device_name'] ?? 'api',
        );
    }
}
