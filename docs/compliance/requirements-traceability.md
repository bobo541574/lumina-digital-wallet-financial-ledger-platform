# Requirements Traceability Matrix

Digital Wallet & Financial Ledger Platform (Lumina)

Status: Baselined for MVP
Maintainer: Engineering (update in the same merge that changes the requirement,
implementation, or verification)

Chain enforced by Part G of BLUEPRINT: requirement ID → implementation → verification.
A row with an empty implementation or verification column is a release blocker.

---

## Legend

- **Implementation**: module/service/route that realizes the requirement.
- **Verification**: test file (or QA record) that proves it. Full paths under `tests/`.

---

## 1. Business requirements

| ID     | Implementation                                    | Verification                                         |
| ------ | ------------------------------------------------- | ---------------------------------------------------- |
| BR-001 | `Identity` AuthController (register/login/me/logout) | tests/Feature/Identity/AuthTest.php                |
| BR-002 | `Identity` RolePermissionSeeder, Permissions enum | tests/Feature/Identity/RolePermissionTest.php       |
| BR-003 | `Wallets` Wallet model, `wallets` + `wallet_balances` tables | tests/Feature/Wallets/WalletTest.php   |
| BR-004 | `Transfers` TransferService currency checks       | tests/Feature/Transfers/TransferTest.php            |
| BR-005 | `Ledger` LedgerPostingService (balanced journal)  | tests/Feature/Ledger/LedgerPostingServiceTest.php   |
| BR-006 | `Shared\Currency`, bigint minor-unit columns      | tests/Unit/MigrationConventionsTest.php              |
| BR-007 | `Deposits` DepositService confirm/verify paths    | tests/Feature/Deposits/DepositTest.php              |
| BR-008 | `Withdrawals` WalletHold + reserved balance       | tests/Feature/Withdrawals/WithdrawalTest.php        |
| BR-009 | `Withdrawals` reconcile + `reconciling` state     | tests/Feature/Withdrawals/WithdrawalTest.php        |
| BR-010 | `Shared\Idempotency` service; `webhook_inbox`     | Transfer/Deposit/Withdrawal tests                   |
| BR-011 | Ledger immutability triggers + ReversalService    | tests/Feature/Reconciliation/FinancialRecoveryTest.php; tests/Feature/Database/DatabaseConstraintTest.php |
| BR-012 | Policies (WalletPolicy/DepositPolicy/WithdrawalPolicy) | WalletTest, AdminTest                          |
| BR-013 | `Audit` AuditLogger on every transition           | tests/Feature/Admin/AdminReviewTest.php             |
| BR-014 | `Reconciliation` WalletReconciliationService      | tests/Feature/Reconciliation/ReconciliationTest.php |
| BR-015 | Wallet statement/export; `ledger` sums            | tests/Feature/History/WalletHistoryTest.php         |
| BR-016 | `Identity` TwoFactorController, RequireTwoFactor  | tests/Feature/Identity/TwoFactorTest.php            |
| BR-017 | docs/architecture/api/openapi.yaml                | tests/Unit/OpenApiContractTest.php                  |
| BR-018 | ResilientTransaction + concurrency tests          | tests/Concurrency/TransferConcurrencyTest.php, tests/Concurrency/WithdrawalConcurrencyTest.php |

## 2. Functional requirements (FR-100…FR-800)

| ID     | Implementation                                        | Verification                                         |
| ------ | ----------------------------------------------------- | ---------------------------------------------------- |
| FR-101 | `AuthController@register` + RegisterRequest            | tests/Feature/Identity/AuthTest.php                  |
| FR-102 | `AuthController@login` + LoginData                     | tests/Feature/Identity/AuthTest.php                  |
| FR-103 | `AuthController@me/logout`                             | tests/Feature/Identity/AuthTest.php                  |
| FR-104 | `TwoFactorController` (enable/confirm/disable/recovery)| tests/Feature/Identity/TwoFactorTest.php             |
| FR-105 | Login 2FA challenge flow                               | tests/Feature/Identity/TwoFactorTest.php             |
| FR-106 | RolePermissionSeeder + `forRole()`                    | tests/Feature/Identity/RolePermissionTest.php        |
| FR-107 | `RequireTwoFactor` middleware                          | tests/Feature/Identity/TwoFactorTest.php             |
| FR-108 | SecurityServiceProvider limiters                       | config/security.php; 429 path in AdminTest           |
| FR-109 | Policies + authorize() calls in every controller      | WalletTest/AdminTest/DepositTest/WithdrawalTest      |
| FR-201 | `WalletService::open` + composite unique keys          | tests/Feature/Wallets/WalletTest.php                 |
| FR-202 | WalletStatus + freeze/unfreeze/close guards            | tests/Feature/Wallets/WalletTest.php                 |
| FR-203 | `WalletBalanceResource` minor units                    | tests/Feature/Wallets/WalletTest.php                 |
| FR-204 | Integer-minor-unit rules everywhere                    | tests/Unit/MigrationConventionsTest.php; tests/Feature/Database/DatabaseConstraintTest.php |
| FR-205 | Balance mutation inside LedgerPostingService tx       | tests/Feature/Ledger/LedgerPostingServiceTest.php    |
| FR-206 | AdminWalletController                                  | tests/Feature/Admin/AdminTest.php                    |
| FR-301 | `TransferService` atomic debit/credit                  | tests/Feature/Transfers/TransferTest.php             |
| FR-302 | Same-currency + active + distinct wallets             | tests/Feature/Transfers/TransferTest.php             |
| FR-303 | Locked balance check (available >= amount+fee)        | tests/Feature/Transfers/TransferTest.php; tests/Concurrency/TransferConcurrencyTest.php; tests/Concurrency/WithdrawalConcurrencyTest.php |
| FR-304 | Idempotent transfer via IdempotencyService            | tests/Feature/Transfers/TransferTest.php             |
| FR-305 | No failed-on-timeout semantics for transfers          | tests/Feature/Transfers/TransferTest.php             |
| FR-306 | Deterministic wallet row locking                      | tests/Feature/Ledger/LedgerPostingServiceTest.php    |
| FR-401 | DepositService manual/provider create                 | tests/Feature/Deposits/DepositTest.php               |
| FR-402 | Manual approve → confirm workflow                     | tests/Feature/Admin/AdminReviewTest.php              |
| FR-403 | Distinct `approved` vs `succeeded` states             | tests/Feature/Deposits/DepositTest.php               |
| FR-404 | Signature verification in webhook pipeline            | tests/Unit/PaymentProviders/SignatureTest.php        |
| FR-405 | `(provider, event_id)` dedupe via webhook_inbox       | tests/Feature/Deposits/DepositTest.php               |
| FR-406 | Amount/currency/reference verification + quarantine   | tests/Feature/Deposits/DepositTest.php               |
| FR-407 | Redirect ≠ success                                    | tests/Feature/Deposits/DepositTest.php               |
| FR-408 | Timeout → reconcile (deposit stays open)              | tests/Feature/Deposits/DepositTest.php               |
| FR-501 | Hold + reserved_balance in one transaction            | tests/Feature/Withdrawals/WithdrawalTest.php         |
| FR-502 | Reserve availability check                            | tests/Feature/Withdrawals/WithdrawalTest.php         |
| FR-503 | Withdrawal state machine                              | tests/Feature/Withdrawals/WithdrawalTest.php         |
| FR-504 | Consume hold + single ledger posting on payout        | tests/Feature/Withdrawals/WithdrawalTest.php         |
| FR-505 | Release hold, no ledger on verified failure           | tests/Feature/Withdrawals/WithdrawalTest.php         |
| FR-506 | `reconciling` retains hold on timeout                 | tests/Feature/Withdrawals/WithdrawalTest.php         |
| FR-507 | Cancel only pending/reserved + safe release           | tests/Feature/Withdrawals/WithdrawalTest.php         |
| FR-508 | Payout idempotency / no double payout                 | tests/Feature/Withdrawals/WithdrawalTest.php         |
| FR-601 | Wallet transactions cursor pagination + filters       | tests/Feature/History/WalletHistoryTest.php          |
| FR-602 | Statement + CSV export                                | tests/Feature/History/WalletHistoryTest.php          |
| FR-603 | Statement totals == ledger                            | tests/Feature/History/WalletHistoryTest.php          |
| FR-604 | AdminController search surface                        | tests/Feature/Admin/AdminTest.php                    |
| FR-605 | Review actions permissioned + audited                 | tests/Feature/Admin/AdminReviewTest.php              |
| FR-606 | LedgerReversalService (reason required)               | tests/Feature/Reconciliation/FinancialRecoveryTest.php |
| FR-607 | AuditEventController restricted to audit.view         | tests/Feature/Admin/AdminTest.php                    |
| FR-608 | Allowlisted filter/sort params                        | Admin/History feature tests                          |
| FR-701 | Outbox record inside financial transaction            | tests/Feature/Outbox/OutboxTest.php                  |
| FR-702 | OutboxProcessor retry/reclaim (at-least-once)         | tests/Unit/Outbox/OutboxServiceTest.php              |
| FR-703 | Monthly partitions + `partitions:ensure`              | tests/Feature/Partitioning/PartitionTest.php; evidence `docs/compliance/review-evidence/partitioning-benchmark.md` |
| FR-704 | Wallet/provider/settlement reconciliation layers     | tests/Feature/Reconciliation/ReconciliationTest.php  |
| FR-705 | Read-only reconciliation + permissioned resolution    | tests/Feature/Reconciliation/ReconciliationTest.php  |
| FR-706 | Single reversal + non-reversible reversal             | tests/Feature/Reconciliation/FinancialRecoveryTest.php |
| FR-801 | Provider adapters (fake/kbzpay/wavemoney/aya/2c2p)    | tests/Feature/PaymentProviders/ProviderCollectTest.php |
| FR-802 | `(provider, provider_ref)` scoped references          | tests/Unit/PaymentProviders/PayoutConfigurationTest.php |
| FR-803 | Config-gated payout adapters                          | tests/Unit/PaymentProviders/PayoutConfigurationTest.php |

## 3. Acceptance criteria (AC-100…AC-500)

Each AC maps to the FR covering the same behaviour; the verification column is the
union of the FR tests listed above plus the frontend evidence produced in the
frontend track (AC-500 series, Phase 13):

| ID     | Verification                                                              |
| ------ | ------------------------------------------------------------------------- |
| AC-101 | WalletTest, TransferTest (cross-customer 404 masking)                      |
| AC-102 | AdminTest (role/permission denials)                                        |
| AC-103 | TwoFactorTest                                                              |
| AC-104 | Backend authorization tests + frontend route-guard tests                  |
| AC-201 | LedgerPostingServiceTest (balanced/unbalanced/currency/duplicate-source)   |
| AC-202 | TransferTest                                                               |
| AC-203 | TransferTest/DepositTest/WithdrawalTest idempotency replay cases          |
| AC-204 | DepositTest + SignatureTest                                                |
| AC-205 | DepositTest (same event_id redelivery)                                     |
| AC-206 | WithdrawalTest                                                             |
| AC-207 | WithdrawalTest (timeout → reconciling, hold intact)                        |
| AC-301 | WalletHistoryTest                                                          |
| AC-302 | WalletHistoryTest/AdminTest (pagination stability, allowlist)              |
| AC-303 | ReconciliationTest                                                         |
| AC-304 | AdminReviewTest/ReconciliationTest (audit records)                         |
| AC-401 | OutboxTest/PartitionTest                                                    |
| AC-402 | ReconciliationTest/FinancialRecoveryTest                                   |
| AC-403 | tests/Concurrency/TransferConcurrencyTest.php, tests/Concurrency/WithdrawalConcurrencyTest.php |
| AC-501..504 | Frontend component + a11y + responsive tests (Phase 03/06–13)          |

## 4. Non-functional requirements (NFR-100…NFR-500)

| ID      | Implementation                                   | Verification / evidence                                     |
| ------- | ------------------------------------------------ | ----------------------------------------------------------- |
| NFR-201 | Service/query design + indexes                   | `composer bench` (`tests/Performance/BenchmarkTest.php`)    |
| NFR-204 | Deterministic row locking (ADR-0003); financial DB constraints | tests/Concurrency/*, tests/Feature/Database/DatabaseConstraintTest.php |
| NFR-206 | Monthly partitioning (ADR-0005)                  | tests/Performance/BenchmarkTest.php; `docs/compliance/review-evidence/partitioning-benchmark.md` |
| NFR-301 | Backups/drills                                   | `docs/operations/deployment.md`                             |
| NFR-302 | Request correlation ids                          | `X-Request-Id` middleware + logging context                 |
| NFR-501 | Traceability maintained                          | This document                                               |
| NFR-502 | Standards applied                                | `docs/compliance/standards-mapping.md`                      |

## 5. BLUEPRINT example mapping → realised

| Example | Realised implementation                                  | Realised verification                          |
| ------- | -------------------------------------------------------- | ---------------------------------------------- |
| FIN-001 | `Ledger\Services\LedgerPostingService`                   | tests/Feature/Ledger/LedgerPostingServiceTest.php |
| FIN-002 | `Shared\Idempotency\IdempotencyService`                  | TransferTest/DepositTest/WithdrawalTest         |
| SEC-001 | Policies + `authorize()` per controller                  | WalletTest/AdminTest/DepositTest/WithdrawalTest |
| UI-001  | Shared design system (frontend track)                    | Frontend component tests                       |
| A11Y-001| Accessible form/dialog components (frontend track)       | Frontend keyboard/a11y tests                   |
| OPS-001 | `PaymentEvents` PartitionManager                         | tests/Feature/Partitioning/PartitionTest.php    |

## 6. Open items (to be filled by the frontend track)

Rows marked `(frontend track)` gain implementation and verification pointers when the
Vue portal (Phases 03, 06–13) ships. Until then they are reported as open in the
frontend status section of this repository.