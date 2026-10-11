/**
 * API types mirroring the backend JSON resources. The success envelope is
 * `{ data: <T>, meta: { request_id: string | null } }` for single resources and
 * `{ data: <T[]>, meta: ... }` for collections (see ApiResponse). Amounts are
 * always integer minor units.
 *
 * Keep in sync with docs/architecture/api/openapi.yaml; the OpenAPI contract
 * test guards the backend side of this contract.
 */

export interface PaginationMeta {
    per_page?: number;
    next_cursor: string | null;
    prev_cursor: string | null;
}

export interface ApiMeta {
    request_id: string | null;
    pagination?: PaginationMeta;
    [key: string]: unknown;
}

export interface ApiItem<T> {
    data: T;
    meta: ApiMeta;
}

export interface ApiCollection<T> {
    data: T[];
    meta: ApiMeta;
}

export interface ApiErrorEnvelope {
    error: {
        code: string;
        message: string;
        details: Record<string, unknown>;
        request_id: string | null;
    };
}

export interface AuthUser {
    id: string;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
    two_factor_enabled: boolean;
    created_at: string | null;
}

export interface AuthTokenResponse {
    user: AuthUser;
    token: string;
}

export type WalletStatus = 'active' | 'frozen' | 'closed';

export interface WalletBalance {
    wallet_id: string;
    currency: string;
    minor_units: number;
    posted_balance: number;
    reserved_balance: number;
    available_balance: number;
}

export interface Wallet {
    id: string;
    user_id: string;
    name: string | null;
    currency: string;
    status: WalletStatus;
    balance?: WalletBalance;
    created_at: string | null;
}

export type TransferStatus = 'pending' | 'succeeded' | 'failed';

export interface Transfer {
    id: string;
    reference: string;
    status: TransferStatus;
    sender_wallet_id: string;
    recipient_wallet_id: string;
    currency: string;
    amount: number;
    fee: number;
    journal_entry_id: string | null;
    failure_code: string | null;
    occurred_at: string | null;
    created_at: string | null;
}

export type WithdrawalStatus =
    'pending' | 'reserved' | 'processing' | 'reconciling' | 'succeeded' | 'failed' | 'cancelled';

export interface Withdrawal {
    id: string;
    reference: string;
    status: WithdrawalStatus;
    wallet_id: string;
    currency: string;
    amount: number;
    fee: number;
    hold_id: string | null;
    provider: string;
    provider_reference: string | null;
    journal_entry_id: string | null;
    failure_code: string | null;
    initiated_at: string | null;
    settled_at: string | null;
    created_at: string | null;
}

export type DepositChannel = 'manual' | 'provider';

export type DepositStatus =
    'pending' | 'approved' | 'succeeded' | 'failed' | 'quarantined' | 'cancelled';

export interface Deposit {
    id: string;
    reference: string;
    status: DepositStatus;
    channel: DepositChannel;
    wallet_id: string;
    currency: string;
    amount: number;
    provider: string | null;
    provider_reference: string | null;
    provider_transaction_id: string | null;
    payment_url: string | null;
    qr_code: string | null;
    journal_entry_id: string | null;
    failure_code: string | null;
    approved_at: string | null;
    settled_at: string | null;
    created_at: string | null;
}

export interface WalletTransaction {
    id: string;
    journal_entry_id: string;
    entry_type: string | null;
    source_type: string | null;
    source_id: string | null;
    description: string | null;
    direction: 'debit' | 'credit';
    amount: number;
    signed_amount: number;
    currency: string;
    occurred_at: string | null;
    created_at: string | null;
}

export interface WalletStatement {
    currency: string;
    from: string;
    to: string;
    opening_balance: number;
    closing_balance: number;
    movements: WalletTransaction[];
}

/** Notifications. */

export interface UserNotification {
    id: string;
    type: string;
    title: string;
    body: string | null;
    data: Record<string, unknown> | null;
    read: boolean;
    read_at: string | null;
    created_at: string | null;
}

/** Admin views. */

export interface AdminUser {
    id: string;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles: string[];
    created_at: string | null;
}

export interface JournalPosting {
    id: string;
    ledger_account_id: string;
    wallet_id: string | null;
    direction: 'debit' | 'credit';
    amount: number;
}

export interface JournalEntry {
    id: string;
    entry_type: string;
    status: string;
    source_type: string | null;
    source_id: string | null;
    description: string | null;
    currency: string;
    occurred_at: string | null;
    postings: JournalPosting[];
}

export interface ProviderTransaction {
    type: 'deposit' | 'withdrawal';
    id: string;
    reference: string;
    provider: string | null;
    provider_reference: string | null;
    provider_transaction_id: string | null;
    status: string;
    amount: number;
    currency: string;
    created_at: string | null;
}

export interface ReconciliationRun {
    id: string;
    layer: string;
    status: string;
    scope: Record<string, unknown> | null;
    summary: Record<string, unknown> | null;
    items_count: number;
    discrepancies_count: number;
    error: string | null;
    initiated_by: string | null;
    started_at: string | null;
    finished_at: string | null;
    items?: ReconciliationItem[];
}

export interface ReconciliationItem {
    id: string;
    run_id: string;
    layer: string;
    classification: string;
    status: string;
    subject_type: string | null;
    subject_id: string | null;
    provider: string | null;
    currency: string;
    difference: number;
    expected: number;
    actual: number;
    resolution: Record<string, unknown> | null;
    resolution_note: string | null;
    resolved_by: string | null;
    resolved_at: string | null;
    created_at: string | null;
}

export interface AuditEvent {
    id: string;
    actor_type: string | null;
    actor_id: string | null;
    action: string;
    subject_type: string | null;
    subject_id: string | null;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    correlation_id: string | null;
    ip_address: string | null;
    occurred_at: string | null;
}

export interface Role {
    id: string;
    name: string;
    label: string;
    is_system: boolean;
    permissions?: string[];
    users_count?: number;
}

export interface Permission {
    name: string;
    label: string;
}

export interface AdminUserDetail {
    id: string;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
    two_factor_enabled: boolean;
    direct_permissions: string[];
    created_at: string | null;
}

