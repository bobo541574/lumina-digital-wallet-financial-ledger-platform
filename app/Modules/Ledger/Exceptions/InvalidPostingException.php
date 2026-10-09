<?php

namespace App\Modules\Ledger\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class InvalidPostingException extends DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(string $message = 'Invalid journal posting.', array $details = [])
    {
        parent::__construct(ErrorCode::InvalidAmount, $message, $details);
    }
}
