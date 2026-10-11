# Risk Register

Digital Wallet & Financial Ledger Platform (Lumina)

Status: Living document — reviewed quarterly and at every release gate
Framework: aligned with ISO/IEC 27001 risk assessment principles (identify, assess,
treat, monitor). Control mappings point to implementation + evidence.

Likelihood/impact scale: 1 (low) … 5 (high). Score = L × I.

---

## 1. Register

| # | Risk | L | I | Score | Treatment (controls) | Implementation / evidence | Residual |
|---|------|---|---|-------|----------------------|---------------------------|----------|
| R-01 | Duplicate money movement (transfer/deposit/withdrawal) | 3 | 5 | 15 | Idempotency keys scoped per actor+operation; payload hash; 24h TTL | `Shared\Idempotency`; Transfer/Deposit/Withdrawal tests | Low |
| R-02 | Overspending under concurrent requests | 3 | 5 | 15 | `SELECT … FOR UPDATE` row locks in deterministic order; availability guard in tx | TransferService/WithdrawalService; `tests/Concurrency/*` | Low |
| R-03 | Unauthorized access to another customer's funds/data | 3 | 5 | 15 | Ownership policies; 404 masking; per-request authorization | WalletPolicy/DepositPolicy; AdminTest | Low-moderate |
| R-04 | Untrusted/fake provider webhook credits a wallet | 2 | 5 | 10 | Signature verify; `(provider,event_id)` dedupe; amount/currency/ref match; quarantine | SignatureTest; DepositTest | Low |
| R-05 | Provider timeout misclassified as failure → wrongful chargeback/release | 3 | 4 | 12 | `reconciling` state; hold retained; verify-no-payout before release | WithdrawalTest; state machines doc | Moderate |
| R-06 | Posted ledger mutated/deleted (fraud or bug) | 2 | 5 | 10 | App guards + DB triggers deny update/delete; reversal-only corrections; least-privilege DB roles | `ledger_immutability` triggers; FinancialRecoveryTest | Low |
| R-07 | Reconciliation mismatch silently auto-corrected | 3 | 4 | 12 | Read-only reconciliation; items require permissioned resolution + audit | ReconciliationTest | Low |
| R-08 | Outbox/queue backlog delays notifications or loses events | 2 | 3 | 6 | Transactional outbox; worker with backoff; reclaim; monitoring alerts | OutboxTest; `operations/monitoring.md` | Low-moderate |
| R-09 | Login brute force / credential stuffing | 3 | 3 | 9 | `throttle:auth` rate limit; strong password defaults; MFA for privileged | SecurityServiceProvider; TwoFactorTest | Low-moderate |
| R-10 | Secrets leakage in logs/audit | 2 | 4 | 8 | Redact_keys masking; no secrets in `.env`; dependency/secret scanning | `config/security.php`; CI audit | Low |
| R-11 | Database unavailable → failed payouts / inconsistent UI | 2 | 4 | 8 | DB timeouts; monitoring; RTO/RPO objectives; backup drills | `operations/deployment.md`; `operations/monitoring.md` | Moderate |
| R-12 | Missing partition on high-volume event table | 2 | 3 | 6 | Default partition; `partitions:ensure` daily; alert on missing month | PartitionTest; monitoring | Low |
| R-13 | Cross-currency confusion (MMK vs USD minor units) | 3 | 4 | 12 | Composite FK (wallet_id, currency_code); single-currency journals; explicit codes in API | LedgerPostingServiceTest; MigrationConventionsTest | Low-moderate |
| R-14 | Frontend shows non-authoritative balance/state | 3 | 2 | 6 | Backend is source of truth; UI renders only; realtime events only trigger refresh | Frontend design rules + tests (frontend track) | Low (once shipped) |

---

## 2. Rating scale reference

| Level | Description |
| ----- | ----------- |
| Low (1–4) | Accept; monitor via existing controls. |
| Moderate (5–11) | Managed by documented controls + monitoring. |
| High (12–25) | Release-blocking until reduced; requires senior sign-off. |

No risk currently sits at High after controls. R-01, R-02, R-03 have raw scores of 15
but are reduced to acceptable residual by the controls listed; the concurrency and
authorization tests must stay green in CI and are re-checked at each release gate.

## 3. Review log

| Date | Reviewed by | Outcome |
| ---- | ----------- | ------- |
| 2026-10-10 | Engineering | Baselined with MVP scope; concurrency tests added to close R-02 verification gap. |