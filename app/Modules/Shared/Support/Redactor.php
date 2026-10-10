<?php

namespace App\Modules\Shared\Support;

/**
 * Masks sensitive values so they never reach logs or audit records. A key is
 * considered sensitive when its (lower-cased) name contains any configured
 * needle, so both `password` and `password_confirmation` are covered.
 */
class Redactor
{
    public const MASK = '[REDACTED]';

    /**
     * @var list<string>
     */
    private readonly array $keys;

    /**
     * @param  list<string>|null  $keys
     */
    public function __construct(?array $keys = null)
    {
        $this->keys = $keys ?? array_values((array) config('security.redact_keys', []));
    }

    /**
     * Recursively mask sensitive values within an array.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function redact(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $redacted[$key] = self::MASK;

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    public function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->keys as $needle) {
            if ($needle !== '' && str_contains($key, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
