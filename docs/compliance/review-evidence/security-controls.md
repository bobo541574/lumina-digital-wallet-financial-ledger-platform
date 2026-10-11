# Evidence — Security Controls Verification

| Field | Value |
| ----- | ----- |
| Date | 2026-10-10 |
| Revision | `eb144b5` + uncommitted working tree |
| Command | `php artisan test --compact` (full suite) |
| Result | Pass — 146 tests, 525 assertions |
| Environment | PHP 8.4.13; PostgreSQL 16.10; Laravel 13.35.0 |

Verification that the controls documented in `docs/security/security-requirements.md`
are exercised by the automated suite. Every row below was executed in this run.

| Control | Requirement | Test evidence | Result |
| ------- | ----------- | ------------- | ------ |
| Authentication (register/login/me/logout) | FR-101..103 | `tests/Feature/Identity/AuthTest.php` | Pass |
| Two-factor auth (enable/confirm/challenge) | FR-104, FR-105, FR-107 | `tests/Feature/Identity/TwoFactorTest.php` | Pass |
| RBAC roles/permissions | FR-106, FR-109 | `tests/Feature/Identity/RolePermissionTest.php` | Pass |
| Ownership / cross-customer 404 masking | FR-109 | `tests/Feature/Wallets/WalletTest.php` | Pass |
| Webhook signature verification | FR-404 | `tests/Unit/PaymentProviders/SignatureTest.php` | Pass |
| Webhook replay / dedupe (`provider`,`event_id`) | FR-405 | `tests/Feature/Deposits/DepositTest.php` | Pass |
| Idempotency on money writes | BR-010 | Transfer/Deposit/Withdrawal feature tests | Pass |
| Payout configuration gating | FR-803 | `tests/Unit/PaymentProviders/PayoutConfigurationTest.php` | Pass |
| Rate limiting (auth/webhooks/money/admin) | FR-108 | 429 assertions in `tests/Feature/Admin/AdminTest.php` | Pass |
| Ledger immutability (tamper attempt blocked) | BR-011 | `tests/Feature/Database/DatabaseConstraintTest.php` | Pass |
| No overspend under concurrency | NFR-204 | `tests/Concurrency/*` | Pass |
| OpenAPI ↔ route contract | BR-017 | `tests/Unit/OpenApiContractTest.php` | Pass |

## Notes & open items

- Rate-limit coverage is verified for the `admin` limiter; add explicit 429 assertions
  for `auth`/`webhooks`/`money` in a follow-up when those endpoints' test fixtures exist.
- 2FA enforcement for privileged admin actions is verified at the middleware level;
  confirm in staging with a real authenticator before enabling `security.two_factor.admin_required`.
- Secrets redaction is configured in `config/security.php` and covered indirectly by
  logging assertions; a dedicated redaction test is a recommended follow-up.
- No penetration test or external security audit has been performed; this is internal
  verification evidence only.