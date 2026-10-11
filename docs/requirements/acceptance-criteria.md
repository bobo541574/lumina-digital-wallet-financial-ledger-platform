# Acceptance Criteria

Digital Wallet & Financial Ledger Platform (Lumina)

Status: Baselined for MVP
Companion: `docs/requirements/functional-requirements.md`,
`docs/business/business-rules.md`

Each acceptance criterion below is a checkable statement; the linked test file (or
manual QA step for frontend/UX, Phase 13) is the evidence. A feature is complete only
when its acceptance criteria hold.

---

## 1. Identity & access (AC-100)

| ID      | Criterion                                                                                     | Evidence |
| ------- | -------------------------------------------------------------------------------------------- | -------- |
| AC-101  | A customer cannot view or mutate another customer's wallet by changing ids in URLs.           | WalletTest, TransferTest (404 masking) |
| AC-102  | Admin-only endpoints reject customers and unauthenticated callers with the documented codes.  | AdminTest |
| AC-103  | Login with an MFA-enabled account without a code returns `401 MFA_REQUIRED`; with a wrong code returns `401 TWO_FACTOR_INVALID_CODE`. | TwoFactorTest |
| AC-104  | Direct UI-route calls cannot bypass backend authorization.                                    | Backend tests + route guard tests (Phase 13) |

## 2. Ledger & money (AC-200)

| ID      | Criterion                                                                                     | Evidence |
| ------- | -------------------------------------------------------------------------------------------- | -------- |
| AC-201  | A balanced journal is posted; an unbalanced, wrong-currency, or duplicate-source journal is rejected. | LedgerPostingServiceTest |
| AC-202  | A successful transfer credits and debits exactly the intended amounts and updates both cached balances atomically. | TransferTest |
| AC-203  | Replaying the same idempotency key moves no money twice and returns the stored result.       | TransferTest, DepositTest, WithdrawalTest |
| AC-204  | A provider deposit is credited only after signature verification and amount/currency match.  | DepositTest, SignatureTest |
| AC-205  | The same provider webhook `event_id` redelivered causes no duplicate wallet credit.           | DepositTest |
| AC-206  | A withdrawal reserve decreases `available_balance`; a confirmed failure releases the hold without a ledger entry. | WithdrawalTest |
| AC-207  | A provider timeout leaves the withdrawal `reconciling` with the hold intact.                 | WithdrawalTest |

## 3. History & admin (AC-300)

| ID      | Criterion                                                                                     | Evidence |
| ------- | -------------------------------------------------------------------------------------------- | -------- |
| AC-301  | Statement totals equal the ledger-derived figures for the period.                            | WalletHistoryTest |
| AC-302  | Cursor pagination is stable across inserts; filters use only allowlisted parameters.         | WalletHistoryTest, AdminTest |
| AC-303  | Resolution of a reconciliation item is exactly once; double resolution is rejected.          | ReconciliationTest |
| AC-304  | Every privileged action writes an auditable record with actor, time, target and reason.      | AdminReviewTest, ReconciliationTest |

## 4. Reliability & recovery (AC-400)

| ID      | Criterion                                                                                     | Evidence |
| ------- | -------------------------------------------------------------------------------------------- | -------- |
| AC-401  | Worker restart, duplicate event, failed delivery and partition rollover do not lose financial data. | OutboxTest, PartitionTest |
| AC-402  | Injected mismatch is detected; its resolution records who, when and why.                     | ReconciliationTest, FinancialRecoveryTest |
| AC-403  | Concurrent requests to one wallet cannot overspend.                                          | `tests/Concurrency/TransferConcurrencyTest.php`, `tests/Concurrency/WithdrawalConcurrencyTest.php` |

## 5. Frontend & UX (AC-500 — Phase 03/06–13)

These are verified in the frontend track; each page/screen ships its own acceptance
table in the frontend docs.

| ID      | Criterion                                                                                     | Evidence |
| ------- | -------------------------------------------------------------------------------------------- | -------- |
| AC-501  | Displayed balance always mirrors the backend's authoritative response; the UI never decides a financial outcome. | Frontend component tests |
| AC-502  | Loading, empty, error, pending, timeout, success and retry states exist for every feature screen. | Frontend design system + state matrix |
| AC-503  | Transfer/deposit/withdrawal confirmation screens show amount, currency, destination and fees. | Frontend component tests |
| AC-504  | Keyboard-only operation and WCAG 2.2 AA-level contrast on supported themes.                  | Frontend accessibility tests (Phase 13) |