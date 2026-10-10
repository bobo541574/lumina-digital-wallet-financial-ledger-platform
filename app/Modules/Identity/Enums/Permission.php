<?php

namespace App\Modules\Identity\Enums;

/**
 * Fine-grained permissions grouped by concern. Seeded and assigned to roles.
 */
enum Permission: string
{
    // Wallets
    case WalletsView = 'wallets.view';
    case WalletsManage = 'wallets.manage';

    // Transfers
    case TransfersCreate = 'transfers.create';
    case TransfersView = 'transfers.view';

    // Deposits
    case DepositsCreate = 'deposits.create';
    case DepositsView = 'deposits.view';
    case DepositsApprove = 'deposits.approve';
    case DepositsConfirm = 'deposits.confirm';

    // Withdrawals
    case WithdrawalsCreate = 'withdrawals.create';
    case WithdrawalsView = 'withdrawals.view';
    case WithdrawalsApprove = 'withdrawals.approve';
    case WithdrawalsProcess = 'withdrawals.process';

    // Ledger & audit
    case LedgerView = 'ledger.view';
    case LedgerCorrect = 'ledger.correct';
    case AuditView = 'audit.view';

    // Reconciliation
    case ReconciliationView = 'reconciliation.view';
    case ReconciliationRun = 'reconciliation.run';

    // Administration
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission) => $permission->value, self::cases());
    }

    /**
     * Permissions granted to a role.
     *
     * @return array<int, self>
     */
    public static function forRole(Role $role): array
    {
        return match ($role) {
            Role::Customer => [
                self::WalletsView,
                self::TransfersCreate,
                self::TransfersView,
                self::DepositsCreate,
                self::DepositsView,
                self::WithdrawalsCreate,
                self::WithdrawalsView,
            ],
            Role::FinanceOperator => [
                self::WalletsView,
                self::TransfersView,
                self::DepositsView,
                self::DepositsApprove,
                self::DepositsConfirm,
                self::WithdrawalsView,
                self::WithdrawalsApprove,
                self::WithdrawalsProcess,
                self::LedgerView,
                self::LedgerCorrect,
                self::ReconciliationView,
                self::ReconciliationRun,
            ],
            Role::Auditor => [
                self::WalletsView,
                self::TransfersView,
                self::DepositsView,
                self::WithdrawalsView,
                self::LedgerView,
                self::AuditView,
                self::ReconciliationView,
            ],
            Role::Admin => self::cases(),
        };
    }
}
