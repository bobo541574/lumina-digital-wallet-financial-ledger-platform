<?php

namespace App\Modules\Wallets\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;

class WalletNotFoundException extends DomainException
{
    /**
     * @param  array<int, string>|string  $walletIds
     */
    public function __construct(array|string $walletIds)
    {
        $ids = (array) $walletIds;

        parent::__construct(
            ErrorCode::WalletNotFound,
            'Wallet not found.',
            ['wallet_ids' => $ids],
        );
    }
}
