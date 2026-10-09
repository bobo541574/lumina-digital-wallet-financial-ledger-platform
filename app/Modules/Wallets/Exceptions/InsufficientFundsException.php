<?php

namespace App\Modules\Wallets\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class InsufficientFundsException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(int $available, int $required, string $currency, array $details = [])
    {
        parent::__construct(
            ErrorCode::InsufficientFunds,
            'Available balance is too low for this operation.',
            ['available' => $available, 'required' => $required, 'currency' => $currency, ...$details],
        );
    }
}
