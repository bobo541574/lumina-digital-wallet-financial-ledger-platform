import { api } from './api/client';
import type {
    AdminUser,
    AdminUserDetail,
    ApiCollection,
    ApiItem,
    AuditEvent,
    Deposit,
    JournalEntry,
    Permission,
    ProviderTransaction,
    ReconciliationItem,
    ReconciliationRun,
    Role,
    Transfer,
    Wallet,
    Withdrawal,
} from '@/types/api';

export type AdminListQuery = Record<string, string | number | boolean | undefined | null>;

export const adminService = {
    wallets: (query: AdminListQuery = {}) =>
        api.get<ApiCollection<Wallet>>('/admin/wallets', { query }).then((r) => r.data),

    wallet: (walletId: string) =>
        api.get<ApiItem<Wallet>>(`/admin/wallets/${walletId}`).then((r) => r.data),

    freezeWallet: (walletId: string) =>
        api.post<ApiItem<Wallet>>(`/admin/wallets/${walletId}/freeze`).then((r) => r.data),

    unfreezeWallet: (walletId: string) =>
        api.post<ApiItem<Wallet>>(`/admin/wallets/${walletId}/unfreeze`).then((r) => r.data),

    users: (query: AdminListQuery = {}) =>
        api.get<ApiCollection<AdminUser>>('/admin/users', { query }).then((r) => r.data),

    user: (userId: string) =>
        api.get<ApiItem<AdminUserDetail>>(`/admin/users/${userId}`).then((r) => r.data),

    updateUserRoles: (userId: string, roles: string[]) =>
        api
            .put<ApiItem<AdminUserDetail>>(`/admin/users/${userId}/roles`, { body: { roles } })
            .then((r) => r.data),

    updateUserPermissions: (userId: string, permissions: string[]) =>
        api
            .put<ApiItem<AdminUserDetail>>(`/admin/users/${userId}/permissions`, {
                body: { permissions },
            })
            .then((r) => r.data),

    roles: () => api.get<ApiCollection<Role>>('/admin/roles').then((r) => r.data),

    role: (roleId: string) => api.get<ApiItem<Role>>(`/admin/roles/${roleId}`).then((r) => r.data),

    updateRolePermissions: (roleId: string, permissions: string[]) =>
        api
            .put<ApiItem<Role>>(`/admin/roles/${roleId}/permissions`, { body: { permissions } })
            .then((r) => r.data),

    permissions: () => api.get<ApiCollection<Permission>>('/admin/permissions').then((r) => r.data),

    deposits: (query: AdminListQuery = {}) =>
        api.get<ApiCollection<Deposit>>('/admin/deposits', { query }).then((r) => r.data),

    withdrawals: (query: AdminListQuery = {}) =>
        api.get<ApiCollection<Withdrawal>>('/admin/withdrawals', { query }).then((r) => r.data),

    transfers: (query: AdminListQuery = {}) =>
        api.get<ApiCollection<Transfer>>('/admin/transfers', { query }).then((r) => r.data),

    journalEntries: (query: AdminListQuery = {}) =>
        api
            .get<ApiCollection<JournalEntry>>('/admin/journal-entries', { query })
            .then((r) => r.data),

    reverseJournalEntry: (journalEntryId: string, body: { reason: string }) =>
        api
            .post<ApiItem<JournalEntry>>(`/admin/journal-entries/${journalEntryId}/reverse`, {
                body,
            })
            .then((r) => r.data),

    providerTransactions: (query: AdminListQuery = {}) =>
        api
            .get<ApiCollection<ProviderTransaction>>('/admin/provider-transactions', { query })
            .then((r) => r.data),

    reconciliationRuns: (query: AdminListQuery = {}) =>
        api
            .get<ApiCollection<ReconciliationRun>>('/admin/reconciliation/runs', { query })
            .then((r) => r.data),

    reconciliationRun: (runId: string) =>
        api
            .get<ApiItem<ReconciliationRun>>(`/admin/reconciliation/runs/${runId}`)
            .then((r) => r.data),

    reconciliationItems: (query: AdminListQuery = {}) =>
        api
            .get<ApiCollection<ReconciliationItem>>('/admin/reconciliation/items', { query })
            .then((r) => r.data),

    runReconciliation: (body: { layer: string; scope?: Record<string, unknown> }) =>
        api
            .post<ApiItem<ReconciliationRun>>('/admin/reconciliation/run', { body })
            .then((r) => r.data),

    resolveReconciliationItem: (
        itemId: string,
        body: { status: string; note: string; resolution?: Record<string, unknown> },
    ) =>
        api
            .post<ApiItem<ReconciliationItem>>(`/admin/reconciliation/items/${itemId}/resolve`, {
                body,
            })
            .then((r) => r.data),

    auditEvents: (query: AdminListQuery = {}) =>
        api.get<ApiCollection<AuditEvent>>('/admin/audit-events', { query }).then((r) => r.data),
};
