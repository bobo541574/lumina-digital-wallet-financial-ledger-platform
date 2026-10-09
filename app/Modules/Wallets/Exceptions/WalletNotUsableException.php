<?php

namespace App\Modules\Wallets\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;
use App\Modules\Wallets\Enums\WalletStatus;
use App\Modules\Wallets\Models\Wallet;

class WalletNotUsableException extends DomainException
{
    public static function forDebit(Wallet $wallet): self
    {
        $code = $wallet->status === WalletStatus::Closed
            ? ErrorCode::WalletClosed
            : ErrorCode::WalletFrozen;

        return new self($code, $wallet, 'debit');
    }

    public static function forCredit(Wallet $wallet): self
    {
        return new self(ErrorCode::WalletClosed, $wallet, 'credit');
    }

    private function __construct(ErrorCode $code, Wallet $wallet, string $operation)
    {
        parent::__construct(
            $code,
            "Wallet [{$wallet->id}] is {$wallet->status->value} and cannot accept a {$operation}.",
            ['wallet_id' => $wallet->id, 'status' => $wallet->status->value, 'operation' => $operation],
        );
    }
}
