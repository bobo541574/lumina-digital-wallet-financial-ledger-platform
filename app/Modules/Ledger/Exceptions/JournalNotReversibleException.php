<?php

namespace App\Modules\Ledger\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class JournalNotReversibleException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(array $details = [])
    {
        parent::__construct(
            ErrorCode::JournalNotReversible,
            'This journal entry cannot be reversed.',
            $details,
        );
    }
}
