# Business Requirements

Digital Wallet & Financial Ledger Platform (Lumina)

Status: Baselined for MVP
Owner: Platform / Finance
Traceability: each requirement maps to functional requirements in
`docs/requirements/functional-requirements.md` and to the traceability matrix in
`docs/compliance/requirements-traceability.md`.

---

## 1. Purpose & scope

A single multi-currency digital wallet platform with a customer portal and an
admin/finance portal, backed by a double-entry ledger as the financial record of
truth. The MVP delivers authenticated customers the ability to hold wallets, move
money internally, deposit, and withdraw, with auditable, reconcilable, and
immutable financial records.

### In scope (MVP)

- User authentication, roles and permissions
- Multi-currency wallets
- Internal wallet-to-wallet transfers
- Manual and payment-provider deposits
- Manual and payment-provider withdrawals
- Withdrawal holds / reserved balance
- Double-entry ledger and immutable postings
- Idempotency and webhook deduplication
- Customer transaction history and statements
- Admin dashboard, transaction review, approvals and audit
- Reconciliation, event partitioning, outbox and operational monitoring

### Out of scope (MVP)

FX exchange, lending, crypto, card acquiring, and cross-border settlement. A
multi-currency wallet does **not** imply currency conversion.

---

## 2. Business requirements

| ID     | Requirement                                                                                             | Priority |
| ------ | ------------------------------------------------------------------------------------------------------- | -------- |
| BR-001 | A user may register, authenticate and manage a session via token.                                       | Must     |
| BR-002 | Access is governed by roles: `customer`, `admin`, `finance_operator`, `auditor`.                         | Must     |
| BR-003 | A customer owns one or more wallets, each bound to exactly one currency.                                | Must     |
| BR-004 | Money moves between wallets of the same currency only (no FX in MVP).                                    | Must     |
| BR-005 | Every money movement is recorded as a balanced double-entry journal.                                    | Must     |
| BR-006 | All monetary amounts are stored and computed as integers in currency minor units.                        | Must     |
| BR-007 | Deposits are credited only after the funds are verified (operator confirm or verified provider webhook). | Must     |
| BR-008 | Withdrawals reserve funds with a hold before payout; the hold is released only once no payout occurred.  | Must     |
| BR-009 | A provider timeout or unknown outcome is never treated as a failure; it enters reconciliation.           | Must     |
| BR-010 | Duplicate requests (same idempotency key) and duplicate webhooks never move money twice.                 | Must     |
| BR-011 | Posted journals and postings are immutable; corrections use compensating reversals.                      | Must     |
| BR-012 | Customers see only their own data; admins see only what their role permits.                              | Must     |
| BR-013 | Every state transition, approval and administrative action is audited.                                   | Must     |
| BR-014 | Cached balances must reconcile to the ledger; discrepancies are detected and reviewed, never overwritten.| Must     |
| BR-015 | Customers can view transaction history and export statements whose totals match the ledger.              | Must     |
| BR-016 | Privileged (admin/finance) accounts support MFA; admin actions can require it.                           | Should   |
| BR-017 | The platform exposes an OpenAPI 3.1 contract for parallel frontend development.                          | Should   |
| BR-018 | Financial operations meet documented concurrency and availability targets.                               | Should   |

---

## 3. Actors

| Actor            | Description                                                        |
| ---------------- | ------------------------------------------------------------------ |
| Customer         | Owns wallets; initiates transfers, deposits, withdrawals.          |
| Admin            | Manages users/wallets and permissions; cannot edit posted postings.|
| Finance operator | Approves/confirms deposits, reviews/processes withdrawals, reconciles. |
| Auditor          | Read-only access to journals, audit events and reports.            |
| Payment provider | External system that collects or disburses funds via adapters.     |

---

## 4. Key business rules (authoritative sources)

Business rules are defined normatively in:

- `docs/business/business-rules.md` — actors, currency, wallet, outcomes, idempotency, providers, fees, audit.
- `docs/business/accounting-rules.md` — double-entry model, chart of accounts, postings, balance projection.
- `docs/business/transaction-state-machines.md` — allowed transitions, guards and side effects.
- `docs/business/permissions-matrix.md` — role-to-permission grants.

---

## 5. Assumptions & open questions

1. Default idempotency key TTL is 24h; it must exceed the provider retry window.
2. Transfer fee defaults to 0 until a fee schedule is approved; fees are posted separately.
3. Segregation of duties: a finance operator may not self-approve their own deposit (enforced).
4. Hold expiry default for withdrawals awaiting reconciliation is set by `hold` policy.
