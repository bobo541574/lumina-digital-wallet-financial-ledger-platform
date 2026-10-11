# Business Rules

Digital Wallet & Financial Ledger Platform

Status: Draft for approval (Phase 00)
Owner: Platform / Finance
Last updated: 2026-10-09

This document is the authoritative source for how money enters, leaves, and moves
through the platform, and when a transaction is considered to have succeeded. It is
referenced by `docs/business/transaction-state-machines.md`,
`docs/business/accounting-rules.md`, and
`docs/architecture/api/error-codes.md`.

---

## 1. Actors and roles

| Role              | Description                                                        |
| ----------------- | ------------------------------------------------------------------ |
| `customer`        | Owns wallets. Can initiate transfers, deposits, withdrawals.       |
| `admin`           | Manages users/wallets, reviews, permissions. Cannot edit postings. |
| `finance_operator`| Approves/confirms deposits, reviews withdrawals, reconciles.       |
| `auditor`         | Read-only access to journals, audit events, reports.               |

Rules:

- A user has one or more roles; effective permissions are the union of role grants.
- `customer` users may only access resources they own (ownership-scoped authorization).
- No role, including `admin`, may update or delete a posted journal or posting.
  Corrections happen only through the controlled reversal workflow.
- Financial approvals require `finance_operator` (or `admin` where explicitly allowed).
- `auditor` has read-only access and is denied every mutating endpoint.

## 2. Currency rules

- Every currency has a unique ISO 4217 `code` (e.g. `USD`, `MMK`) and a `minor_units`
  count. `minor_units` is the number of decimal places stored as integers.
  - USD: `minor_units = 2` → 1 USD = 100 minor units.
  - MMK: `minor_units = 0` → 1 MMK = 1 minor unit.
- **All monetary amounts are stored and computed as signed 64-bit integers in minor
  units.** No floats are ever used for money.
- A wallet is bound to exactly one currency and cannot change currency after creation.
- A single journal entry and all of its postings are in exactly one currency. A single
  financial operation must not mix currencies. Cross-currency movements are out of MVP
  scope and require an explicit FX design.
- Amounts in a journal are stored as positive magnitudes together with a direction;
  sign is derived from direction, never from a negative magnitude.

## 3. Wallet rules

Wallet statuses: `active`, `frozen`, `closed`.

- `active`: normal operations allowed.
- `frozen`: no debits may originate (no transfers out, no withdrawals reserve). Credits
  (deposits, incoming transfers, reversals) are allowed so funds can still land. Admin
  freeze/unfreeze is audited.
- `closed`: no operations allowed at all.

A wallet is uniquely identified by `(user_id, currency_code)` semantics enforced at the
model layer; storage enforces `wallets (id, currency_code)` as a composite unique key so
that related financial rows can carry a composite foreign key
`(wallet_id, currency_code)` that guarantees wallet/currency consistency.

Balance definitions (per wallet):

- `posted_balance`: sum of all posted ledger movements for the wallet's ledger account.
  This is the operational/cached balance.
- `reserved_balance`: sum of active (unreleased, unexpired) holds. Represented in
  `wallet_balances.reserved_balance` and reconciled against `wallet_holds`.
- `available_balance = posted_balance − reserved_balance`.

Invariants:

- `reserved_balance >= 0` and `reserved_balance <= posted_balance` are enforced.
- A debit (outgoing) operation is only allowed when
  `available_balance >= amount + fees`.
- The cached `wallet_balances` row is only ever mutated inside the same database
  transaction that posts the corresponding ledger entry. It is never updated by
  asynchronous workers.
- `wallet_balances` positions are derived from, and must always reconcile against, the
  ledger (see `docs/business/accounting-rules.md` §7).

## 4. Transaction outcomes

- `pending`: created, not yet acted upon.
- `processing`: an external side effect has been initiated; outcome may be unknown.
- `succeeded`: the operation is confirmed complete and ledger + balance reflect it.
- `failed`: confirmed unsuccessful; no money moved (or reversed).
- `cancelled`: abandoned before completion (customer or admin action).
- `reconciling`: outcome unknown (typically provider timeout). **Not** a failure.

Rules:

- A provider timeout is **never** treated as a failure. It transitions to `reconciling`
  (withdrawals) or remains awaiting webhook with a scheduled reconciliation (deposits).
- Only `succeeded` operations post irreversible ledger movements.
- Approval is not settlement. For deposits, `approved` means a finance operator has
  verified the request; `succeeded` means funds were actually received and posted. These
  are distinct and must not be conflated.

## 5. Idempotency rules

Idempotency is scoped per actor + operation, using a client-supplied `Idempotency-Key`.

Scope formula: `(user_id|actor, operation, idempotency_key)`.

- Same key + same request payload hash + completed → return the original result
  (same status code and body). No additional money movement occurs.
- Same key + same payload hash + still in progress → return `409 Conflict` with a
  retryable marker (`IDEMPOTENCY_IN_PROGRESS`), or `202 Accepted` per endpoint policy.
- Same key + **different** payload hash → return `422`/`409` `IDEMPOTENCY_KEY_REUSED`.
  The original result is unchanged.
- Idempotency records store: scope, key, request hash, response status, response body
  (or reference), resource id, and expiry.
- Idempotency keys expire after a configurable window (default 24h), after which reuse is
  permitted; retention must exceed provider retry windows.
- Webhook processing is additionally idempotent on provider `event_id` via
  `webhook_inbox` (see `docs/business/transaction-state-machines.md` §5).

## 6. Provider integration rules

- Every provider interaction carries our `reference` and the provider's `provider_ref`.
- `provider_transactions` (or equivalent) uniquely scopes provider references by provider:
  uniqueness is `(provider, provider_ref)`, not globally.
- Webhook signatures are verified before any state change. Invalid signature → rejected,
  recorded, no posting.
- Webhook payload amount, currency, and merchant/order reference are verified against the
  expected intent before posting. Mismatch → quarantined for reconciliation, no posting.
- Duplicate webhook (same provider `event_id`) → safe `2xx` acknowledgement, no second
  posting.
- Unknown or out-of-order provider statuses are stored and reconciled rather than guessed.
- Refunds/reversals from the provider are modeled as new, explicit ledger entries; they
  never mutate prior entries.

## 7. Fees

- Fees are separate ledger postings, never netted silently into the principal amount.
- A transfer fee, where applicable, debits the sender's wallet liability and credits the
  platform fee revenue account.
- Fee configuration is versioned; the fee applied is recorded on the operation so history
  remains explainable.

## 8. Audit and immutability

- Every state transition, approval, and administrative action writes an `audit_events`
  record (actor, action, subject, before/after, correlation id, ip, timestamp).
- Posted `journal_entries` and `ledger_postings` are append-only. Database triggers deny
  `UPDATE` and `DELETE`.
- Corrections are performed by posting a reversal journal that exactly offsets the
  original, linked via `reverses_journal_entry_id`. The original remains untouched.

## 9. Failure, timeout, duplicate, refund/reversal rules

| Situation                     | Rule                                                                                 |
| ----------------------------- | ------------------------------------------------------------------------------------ |
| Provider timeout on deposit   | Keep intent open; reconcile via status query/webhook. Never auto-fail or auto-post. |
| Provider timeout on withdrawal| Transition to `reconciling`; keep hold; reconcile. Never release hold while unknown.  |
| Duplicate webhook             | Deduplicate on `(provider, event_id)`; acknowledge; no second posting.               |
| Invalid signature             | Reject + record; no state change.                                                     |
| Amount/currency mismatch      | Quarantine for reconciliation; no posting.                                            |
| Confirmed failure (deposit)   | No posting; request marked `failed`.                                                 |
| Confirmed failure (withdrawal)| Release hold only after confirming no payout occurred.                               |
| Refund / reversal             | New reversing journal entry; original entries unchanged.                             |
| Ledger/balance discrepancy    | Never auto-correct. Investigate; resolve via approved correction workflow.          |

## 10. Authorization rules

- All customer endpoints are ownership-scoped. Changing a resource id in a URL must never
  expose another user's data.
- Admin/finance endpoints require the appropriate role; write actions are audited.
- Read access to audit logs is restricted to `auditor`/`admin`.
- MFA is required for admin/finance roles (design in Phase 09).
- Rate limiting applies per actor and per idempotency scope.

## 11. Non-goals (MVP)

FX exchange, lending, crypto, card acquiring, and cross-border settlement are out of
scope. A multi-currency wallet does not imply exchange capability.

## 12. Open questions for approval

1. Default idempotency key TTL and webhook retry window alignment.
2. Exact fee schedule and who bears transfer fees by default.
3. Whether `finance_operator` may self-approve their own initiated deposits (segregation
   of duties). Proposed: no.
4. Hold expiry default for withdrawals awaiting reconciliation.
