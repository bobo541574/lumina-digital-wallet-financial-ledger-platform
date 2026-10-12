# Data Model Overview

Core entities and relationships (high-level).

## Core tables

| Table | Purpose | Notes |
|---|---|---|
| `users` | Customers, admins, finance operators, auditors | UUID PK; roles/permissions via pivots |
| `wallets` | Customer currency wallets | UUID PK; FK to users (restrict); has liability account |
| `wallet_balances` | Cached balance projection | 1:1 with wallet; posted/reserved (integers, minor units) |
| `ledger_accounts` | Chart of accounts | System (CASH, FEE_REVENUE, clearing) + wallet liability accounts |
| `journal_entries` | Double-entry journals | Immutable once posted; balanced per currency; (source_type,source_id) unique |
| `ledger_postings` | Journal lines (debit/credit) | FK to journal + account; wallet_id set for wallet accounts |
| `deposits` | Deposit requests/settlements | Manual/provider flows; terminal states enforced |
| `withdrawals` | Withdrawal requests/payouts | Linked to holds; full amount+fee reserved |
| `wallet_holds` | Reserved funds | Active/consumed/released/expired; affects reserved_balance |
| `transfers` | Internal transfers | References sender/recipient wallets; posts transfer journal |
| `audit_events` | Immutable audit trail | Redacted payloads, correlation_id, actor |
| `outbox_messages` | Transactional outbox | Pending/processing/processed/failed; at-least-once delivery |
| `webhook_inbox` | Provider webhook deduplication | (provider,event_id) unique; quarantined on mismatch |
| `reconciliation_runs` | Reconciliation executions | Per-layer; captures discrepancies count/errors |
| `reconciliation_items` | Discrepancy records | Read-only classification/resolution |
| `payment_events` | Append-only payment events | Monthly partitioned by occurred_at; (id,occurred_at) PK |
| `idempotency_keys` | Idempotency store | (scope,actor_id,key) lookup; stores response body |

## Key invariants

- All amounts are integers (minor units). Never floats.
- Every journal balanced (debits sum == credits) in same currency.
- Wallet liability postings only affect wallets that own the account.
- Holds only consumed on successful settlement; released on verified failure/cancel.
- Financial FKs use `RESTRICT ON DELETE`; technical pivots use `CASCADE`.
- Postings/journals append-only (immutable model).
