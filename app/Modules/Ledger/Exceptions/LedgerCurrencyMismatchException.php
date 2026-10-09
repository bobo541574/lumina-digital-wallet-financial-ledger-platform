<?php

namespace App\Modules\Ledger\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class LedgerCurrencyMismatchException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(array $details = [])
    {
        parent::__construct(
            ErrorCode::CurrencyMismatch,
            'Journal and posting currencies must all match.',
            $details,
        );
    }
}
