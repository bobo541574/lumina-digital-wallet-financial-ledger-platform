<?php

namespace App\Modules\Ledger\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Revenue = 'revenue';
    case Expense = 'expense';
    case Equity = 'equity';

    /**
     * Whether a debit increases this account's balance (normal balance side).
     */
    public function isDebitNormal(): bool
    {
        return match ($this) {
            self::Asset, self::Expense => true,
            self::Liability, self::Revenue, self::Equity => false,
        };
    }
}
