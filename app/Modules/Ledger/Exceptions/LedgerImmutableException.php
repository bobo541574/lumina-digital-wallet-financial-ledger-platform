<?php

namespace App\Modules\Ledger\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class LedgerImmutableException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(string $message = 'Posted ledger entries are immutable.', array $details = [])
    {
        parent::__construct(ErrorCode::LedgerImmutable, $message, $details);
    }
}
