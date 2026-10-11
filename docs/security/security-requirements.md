# Security Requirements & Controls

Scope: customer funds and data (risks R-01…R-10). This page is the security entry
point; the authoritative requirement IDs live in `docs/requirements/non-functional-requirements.md`
(NFR-200 series).

## 1. Authentication & session

- Sanctum token auth (`Bearer`); login throttled by `throttle:auth` limiter.
- 2FA (TOTP) mandatory for privileged actions via `RequireTwoFactor` middleware;
  recovery codes; login challenge when enabled.
- Tokens are revoked on logout/password change; sessions are short-lived.
- Password policy enforced (min length, non-pwned where applicable).

## 2. Authorization

- Policies at the object level: `WalletPolicy`, `DepositPolicy`, `WithdrawalPolicy`…
- 404-masking for cross-customer access (no existence oracle).
- Roles/permissions centralised in `Identity\Enums`; enforced in code, not just UI.
- Admin surfaces gated to `admin.*`/`audit.view` permissions.

## 3. Data protection

- Secrets only in `.env*` (never committed); `config/security.php` `redact_keys`
  masking applied to logs/audit responses.
- Least-privilege DB credentials; no app user writes to partitions/triggers.
- All money fields read/written as integers (no float) — ADR-0001.

## 4. API & webhook hardening

- OpenAPI contract enforced against registered routes (`OpenApiContractTest`).
- Webhook requests verified by provider signature; `(provider, event_id)` dedupe;
  redirect never implies success.
- Rate limiting: `auth`, `webhooks`, `money`, `admin` limiters; per-user/per-IP limits.

## 5. Monitoring & incident

- Alerts for auth failures, quarantine, reconciliation variance, outbox backlog,
  partitioning gaps — `docs/operations/monitoring.md`.
- Incident runbooks — `docs/runbooks/`.

## 6. Enforced by tests

| Control | Test |
| ------- | ---- |
| Signatures | tests/Unit/PaymentProviders/SignatureTest.php |
| 2FA flows | tests/Feature/Identity/TwoFactorTest.php |
| RBAC | tests/Feature/Identity/RolePermissionTest.php |
| Ownership/404 masking | tests/Feature/Wallets/WalletTest.php |
| Rate limits | 429 assertions in tests/Feature/Admin/AdminTest.php |
| Provider payout config | tests/Unit/PaymentProviders/PayoutConfigurationTest.php |
| Financial DB constraints + ledger immutability | tests/Feature/Database/DatabaseConstraintTest.php |
| Concurrency overspend guards | tests/Concurrency/TransferConcurrencyTest.php, tests/Concurrency/WithdrawalConcurrencyTest.php |