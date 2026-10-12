# Business Flow

This document describes the end-to-end business flows for Lumina's digital wallet platform. All flows enforce double-entry accounting, idempotency, and state machine rules defined in `transaction-state-machines.md` and `accounting-rules.md`.

## 1. User onboarding & access

1. **Registration**: Customer registers via `/auth/register`. Email verification required before accessing sensitive operations (per NFRs).
2. **Authentication**: Login via `/auth/login` returns Sanctum token. MFA may be enabled/confirmed for admin accounts depending on `security.two_factor.admin_required`.
3. **Roles & permissions**: RBAC enforced at policy/middleware level. Admin assigns roles/permissions; ownership enforced for customer-scoped resources.
4. **Users**: Admin can manage users, roles, permissions (`/admin/users`, `/admin/roles`).

## 2. Wallet lifecycle

1. **Open wallet**: Customer opens a wallet for an active currency (`POST /wallets`). Each wallet gets exactly one liability ledger account (`CUSTOMER_WALLET`) and one `wallet_balances` row (posted=0, reserved=0).
2. **Balance model**: `posted_balance` = sum of signed ledger postings affecting the wallet's liability account (credits increase). `reserved_balance` = sum of active holds. `available = posted_balance - reserved_balance`.
3. **Freeze/Unfreeze/Close** (admin): Admin can freeze/unfreeze customer wallets (`POST /admin/wallets/{id}/freeze|unfreeze`). Frozen wallets cannot debit (transfers out, withdrawals). Closed wallets cannot credit.
4. **Ledger anchoring**: All wallet-affecting movements produce balanced journal entries + postings; `projectWalletBalances()` updates cached balances inside the same transaction.

## 3. Deposits

Deposits credit customer wallets via two paths:

### 3.1 Manual deposits (finance-approved)

1. **Create**: Customer requests manual deposit (`POST /deposits`) → `awaiting_approval`, no ledger posting, balance unchanged. Idempotency-key enforced.
2. **Approve**: Finance operator approves (`POST /deposits/{id}/approve`) → `approved`. Initiator cannot self-approve (segregation of duties).
3. **Confirm**: Finance operator confirms receipt (`POST /deposits/{id}/confirm`) → `succeeded`. Creates journal entry:
   - Dr: `CASH` (system asset, currency)
   - Cr: wallet liability account (customer wallet)
   Wallet balance credited on posting; outbox event `deposit.succeeded` recorded.
4. **Reject/Cancel**: Per state machine; terminal states prevent replays.

### 3.2 Provider deposits (webhook-driven)

1. **Create intent**: Customer creates provider intent (`POST /deposits/provider`) → adapter returns `PaymentIntentResult`. Deposit persisted as `awaiting_payment`/`processing` with provider refs; if provider reports sync success, may settle immediately.
2. **Webhook**: Provider calls webhook (`POST /payment-providers/{provider}/webhook`). Pipeline: verify HMAC signature, dedupe via `webhook_inbox` (provider,event_id), validate amount/currency/reference match, then settle/mark failed. Mismatches → `reconciling` + quarantine (no auto-post).
3. **Settlement**: On `succeeded`, identical double-entry as manual confirm (Dr system clearing/asset per provider flow, Cr wallet liability). Duplicate webhook deliveries are idempotent.

## 4. Internal transfers (wallet-to-wallet)

1. **Request**: Customer initiates transfer (`POST /transfers`) with Idempotency-Key + request hash. Ownership enforced (sender wallet must belong to actor). Same-currency required. Self-transfer rejected.
2. **Atomic execution**: Lock sender+recipient wallets/balances in deterministic order (`wallet_id` asc). Validate `available(sender) >= amount + fee`. Validate recipient status allows credit.
3. **Posting**: Create journal entry `transfer`:
   - Dr: sender wallet liability account (`amount + fee`)
   - Cr: recipient wallet liability account (`amount`)
   - Cr: `FEE_REVENUE` (system revenue, currency) for fee portion (if > 0)
4. **Commit**: Update statuses, record outbox event `transfer.completed` (payload includes both wallet ids). On replay with same key, return stored result.
5. **Notifications**: Project to sender (`transfer.sent`) and recipient (`transfer.received`).

## 5. Withdrawals (with holds)

1. **Request**: Customer requests withdrawal (`POST /withdrawals`) → creates withdrawal, reserves full outflow (`amount + fee`) via holds. Hold active, `reserved_balance` increased, status `reserved`. Available drops immediately.
2. **Initiate**: Initiate payout (`POST /withdrawals/{id}/initiate`) → calls payout provider.
   - Success → may transition to `processing`/`succeeded` per provider.
   - Provider timeout → do not release hold; mark `reconciling`.
   - Explicit failure → release hold, mark `failed`.
3. **Webhook settlement**: Payout webhooks settle succeeded/failed; unknown status acked. On success: post double-entry (Dr wallet liability `amount+fee`, Cr payout clearing `amount`, Cr fee revenue `fee` if any), consume hold, emit `withdrawal.succeeded`. On failure: release hold, emit `withdrawal.failed`.
4. **Cancel**: Only `pending`/`reserved` cancellable → release hold, mark `cancelled`.
5. **States**: `pending → reserved → processing → succeeded|failed|reconciling|cancelled`. Holds only consumed on success; released on verified failure/cancel.

## 6. Ledger & postings (double-entry)

- Journal entries immutable once posted; balanced in same currency.
- Wallet-scoped postings match wallet liability account; currency consistency enforced.
- `projectWalletBalances()` applies signed deltas inside transaction.
- `(source_type, source_id)` unique; reversals invert original postings with single-reversal constraint.

## 7. History, statements & admin

- **History**: `GET /wallets/{id}/transactions` returns unified ledger-derived history (cursor pagination, date/type filters).
- **Statements**: `GET /wallets/{id}/statement` returns opening/closing balances + movements.
- **Admin**: Manage deposits/withdrawals/transfers/journal entries, run reconciliation, resolve items, reverse journals, manage users/roles/permissions.

## 8. Outbox, events & notifications

1. **Transactional outbox**: Record messages inside same transaction as state changes.
2. **Delivery**: `outbox:process` claims via `FOR UPDATE SKIP LOCKED`, retries with backoff, fails after max attempts.
3. **Projection**: `ProjectDomainEventToNotification` creates in-app notifications (deduped by user_id,event_id), broadcasts on create.

## 9. Reconciliation

- **Layer 1 (Wallet)**: Recompute posted from ledger, reserved from active holds; compare to balances.
- **Layer 2/3 (Provider/Settlement)**: Compare platform vs provider/settlement; record discrepancies.
- **Runs**: Per-layer or all; errors captured on run; items resolvable by admin. Never auto-corrected.

## 10. Data integrity & safety

- Deterministic locking by id/wallet_id asc; resilient transactions for financial writes.
- DB timeouts configurable; webhook quarantine on verification/match failures.
- Hold safety: consumed only on success, released on verified failure/cancel; timeouts → reconciliation.
- Audit logging with redaction, correlation id, actor, IP.
- Partitioning: `payment_events` monthly (hybrid for others).
