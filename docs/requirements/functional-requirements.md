# Functional Requirements

Digital Wallet & Financial Ledger Platform (Lumina)

Status: Baselined for MVP
Owner: Product / Engineering
Companion: `docs/requirements/business-requirements.md`,
`docs/requirements/acceptance-criteria.md`

Functional requirements carry IDs referenced by implementation (routes, services)
and by the traceability matrix in `docs/compliance/requirements-traceability.md`.
Verification column names the test/behaviour that proves the requirement.

---

## 1. Identity, authentication & access (FR-100 series)

| ID      | Requirement                                                                          | Verification |
| ------- | ------------------------------------------------------------------------------------ | ------------ |
| FR-101  | Register a customer with name, email, password (validated, confirmed).               | Register API + AuthTest |
| FR-102  | Login with email/password returns a bearer token and the user profile + permissions. | Login API + AuthTest |
| FR-103  | `/auth/me` returns the current user; logout revokes the current token.               | AuthTest |
| FR-104  | A user can enable, confirm, disable 2FA and regenerate recovery codes.               | TwoFactorTest |
| FR-105  | If a user has 2FA enabled, login requires a valid TOTP code.                         | TwoFactorTest |
| FR-106  | Roles are `customer`, `admin`, `finance_operator`, `auditor`; effective permissions are the union of grants. | RolePermissionTest |
| FR-107  | Admin/finance mutation endpoints require a confirmed TOTP when `SECURITY_ADMIN_MFA_REQUIRED=true`. | TwoFactorTest |
| FR-108  | Auth login/register and webhook endpoints are rate limited; overflow returns `429 RATE_LIMITED` with `Retry-After`. | Security provider config |
| FR-109  | Resource ownership and permission checks run on every request, not only in the UI.   | Wallet/Admin feature tests |

## 2. Wallets & balances (FR-200 series)

| ID      | Requirement                                                                            | Verification |
| ------- | -------------------------------------------------------------------------------------- | ------------ |
| FR-201  | A customer opens a wallet for a supported currency; wallets are uniquely keyed by `(user_id, currency_code)`. | WalletTest |
| FR-202  | Wallet status is `active`, `frozen` or `closed`; frozen and closed restrict operations per rules. | WalletTest |
| FR-203  | Balance endpoints expose `posted_balance`, `reserved_balance`, `available_balance` in minor units with currency metadata. | WalletTest |
| FR-204  | Amounts are integers in minor units; the API never accepts or emits floats for money. | WalletTest + Request rules |
| FR-205  | Cached balances are mutated only inside the same transaction as the ledger posting.   | LedgerPostingServiceTest |
| FR-206  | Admins can list wallets, view a wallet, freeze and unfreeze (audited).                 | AdminTest |

## 3. Internal transfers (FR-300 series)

| ID      | Requirement                                                                                | Verification |
| ------- | ------------------------------------------------------------------------------------------ | ------------ |
| FR-301  | Transfer debits the sender wallet and credits the recipient wallet in one atomic journal.  | TransferTest |
| FR-302  | Source and destination must be active, same currency, and different wallets.               | TransferTest |
| FR-303  | Transfer requires `available_balance >= amount + fee`, enforced under wallet row locks.    | TransferTest + `tests/Concurrency/TransferConcurrencyTest.php` |
| FR-304  | Transfers are idempotent via `Idempotency-Key`; replays return the original result.        | TransferTest |
| FR-305  | Client timeout does not imply failure; the request state is authoritative.                 | TransferTest + state docs |
| FR-306  | Sender/recipient wallet rows are locked in deterministic order to avoid deadlocks.         | LedgerPostingServiceTest |

## 4. Deposits (FR-400 series)

| ID      | Requirement                                                                                      | Verification |
| ------- | ------------------------------------------------------------------------------------------------ | ------------ |
| FR-401  | A customer creates a manual deposit (awaiting approval) or a provider deposit (payment intent).  | DepositTest |
| FR-402  | Manual deposits require operator approval, then confirmation of received funds before credit.     | DepositTest |
| FR-403  | Approval and settlement are distinct states; `approved != succeeded`.                            | DepositTest |
| FR-404  | Provider webhooks are signature-verified before any state change.                                | SignatureTest + DepositTest |
| FR-405  | Webhooks deduplicate on `(provider, event_id)`; redelivery is acknowledged without re-posting.   | DepositTest |
| FR-406  | Webhook amount/currency/reference are verified against the intent; mismatch quarantines.         | DepositTest |
| FR-407  | Client redirect success alone is never treated as payment success.                               | DepositTest |
| FR-408  | Provider timeout / unknown outcome keeps the intent open for reconciliation.                    | DepositTest |

## 5. Withdrawals (FR-500 series)

| ID      | Requirement                                                                                  | Verification |
| ------- | -------------------------------------------------------------------------------------------- | ------------ |
| FR-501  | A withdrawal request creates a hold and increments `reserved_balance` in the same transaction.| WithdrawalTest |
| FR-502  | Reserve requires `available_balance >= amount + fee`.                                        | WithdrawalTest |
| FR-503  | Payout proceeds through `pending → reserved → processing → succeeded` (or failure paths).     | WithdrawalTest |
| FR-504  | On confirmed payout, a single ledger posting consumes the hold and decrements `reserved_balance`. | WithdrawalTest |
| FR-505  | On confirmed failure (verified no payout), the hold is released with no ledger entry.          | WithdrawalTest |
| FR-506  | On provider timeout the withdrawal becomes `reconciling` and the hold is retained.            | WithdrawalTest |
| FR-507  | A customer can cancel only `pending`/`reserved` withdrawals; release is safe (verified no payout). | WithdrawalTest |
| FR-508  | Payout is idempotent and protected against double payout.                                    | WithdrawalTest |

## 6. History, statements & admin (FR-600 series)

| ID      | Requirement                                                                                 | Verification |
| ------- | ------------------------------------------------------------------------------------------- | ------------ |
| FR-601  | A customer views transactions with cursor pagination and `from/to/type` filters.             | WalletHistoryTest |
| FR-602  | A customer views a statement (opening, movements, closing) and exports CSV.                  | WalletHistoryTest |
| FR-603  | Statement totals match ledger-derived figures.                                               | WalletHistoryTest |
| FR-604  | Admins search users, wallets, transfers, journals, deposits, withdrawals, providers.        | AdminTest |
| FR-605  | Admin review actions (approve/reject/confirm/freeze) are permissioned and audited.           | AdminReviewTest |
| FR-606  | Journals can be reversed through the controlled `ledger.correct` workflow with a reason.     | FinancialRecoveryTest |
| FR-607  | Audit events are queryable by privileged roles only.                                        | AdminTest |
| FR-608  | Filters/sorts use allowlisted query parameters only.                                        | Admin/History tests |

## 7. Events, reliability & reconciliation (FR-700 series)

| ID      | Requirement                                                                                   | Verification |
| ------- | --------------------------------------------------------------------------------------------- | ------------ |
| FR-701  | Financial writes publish outbox messages inside the same transaction (transactional outbox).  | OutboxTest |
| FR-702  | Outbox worker delivers at-least-once with retry/backoff and reclaims stale `processing`.      | OutboxTest + OutboxServiceTest |
| FR-703  | `payment_events` is partitioned monthly; missing partitions are reportable.                   | PartitionTest |
| FR-704  | Reconciliation detects wallet/ledger, reserved/holds, provider and settlement mismatches.     | ReconciliationTest |
| FR-705  | Discrepancies are never auto-corrected; resolution is permissioned and audited.               | ReconciliationTest + FinancialRecoveryTest |
| FR-706  | A journal is reversible at most once; a reversal cannot be reversed.                          | FinancialRecoveryTest |

## 8. Webhooks & providers (FR-800 series)

| ID      | Requirement                                                                                    | Verification |
| ------- | ---------------------------------------------------------------------------------------------- | ------------ |
| FR-801  | Provider adapters (`fake`, `kbzpay`, `wavemoney`, `aya`, `2c2p`) normalize collect/payout.      | ProviderCollectTest |
| FR-802  | Provider references are scoped `(provider, provider_ref)`, not globally unique.                 | PayoutConfigurationTest |
| FR-803  | Payout adapters are config-gated until a payout endpoint is set.                                | PayoutConfigurationTest |

---

## 9. Requirement traceability handoff

Every FR-xx above is linked to its implementation (services/controllers/routes) and
to the passing test file in `docs/compliance/requirements-traceability.md`. New
features must add an FR id, an implementation pointer and a verification pointer in
the same merge.