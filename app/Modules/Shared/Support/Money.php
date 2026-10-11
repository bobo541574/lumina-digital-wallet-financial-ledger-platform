<?php

namespace App\Modules\Shared\Support;

use App\Modules\Shared\Models\Currency;

/**
 * Formats integer minor units into a human-readable amount, e.g.
 * `Money::format(5000, 'USD')` => `"USD 50.00"`. Falls back to two decimal
 * places when the currency is not registered.
 */
final class Money
{
    public static function format(int $minorUnits, string $currency): string
    {
        $exponent = (int) (Currency::query()->whereKey($currency)->value('minor_units') ?? 2);
        $major = $exponent === 0 ? $minorUnits : $minorUnits / (10 ** $exponent);

        return sprintf('%s %s', $currency, number_format($major, $exponent, '.', ','));
    }
}
