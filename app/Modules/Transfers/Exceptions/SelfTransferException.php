<?php

namespace App\Modules\Transfers\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class SelfTransferException extends DomainException
{
    public function __construct(string $walletId)
    {
        parent::__construct(
            ErrorCode::SelfTransfer,
            'A wallet cannot transfer to itself.',
            ['wallet_id' => $walletId],
        );
    }
}
