<?php

namespace App\Modules\Identity\Enums;

/**
 * Platform roles, stored by name in the roles table.
 */
enum Role: string
{
    case Customer = 'customer';
    case Admin = 'admin';
    case FinanceOperator = 'finance_operator';
    case Auditor = 'auditor';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
