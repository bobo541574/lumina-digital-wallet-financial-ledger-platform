<?php

namespace App\Modules\Ledger\Enums;

enum PostingDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function opposite(): self
    {
        return $this === self::Debit ? self::Credit : self::Debit;
    }

    /**
     * Signed effect on an account with the given normal-balance side.
     */
    public function signedAmountFor(AccountType $type, int $amount): int
    {
        $increases = $this === ($type->isDebitNormal() ? self::Debit : self::Credit);

        return $increases ? $amount : -$amount;
    }
}
