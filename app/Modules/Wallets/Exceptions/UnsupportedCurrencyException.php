<?php

namespace App\Modules\Wallets\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class UnsupportedCurrencyException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(string $currency, array $details = [])
    {
        parent::__construct(
            ErrorCode::UnsupportedCurrency,
            "Currency [{$currency}] is not supported.",
            ['currency' => $currency, ...$details],
        );
    }
}
