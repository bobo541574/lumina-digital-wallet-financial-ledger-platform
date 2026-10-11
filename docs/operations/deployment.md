# Deployment & Operations

## Requirements
- PHP 8.4, PostgreSQL 16, Redis 7+, Node 20+ (for asset build).
- Queue worker (`php artisan queue:work` or Horizon), and scheduler (`php artisan schedule:work`) in all non-local envs.
- Realtime (optional): a long-running Reverb server (`php artisan reverb:start`) plus its public WebSocket endpoint when `BROADCAST_CONNECTION=reverb`.
- `composer install --no-dev --optimize-autoloader` on deploy; run `php artisan config:cache`, `route:cache`, `view:cache` as appropriate.
- Set secure secrets via environment (never commit `.env`). Rotate API keys/credentials on rotation.

## Environment setup
- Copy `.env.example` → `.env`, fill DB/Redis/mail/provider secrets.
- Generate `APP_KEY`: `php artisan key:generate`.
- Run migrations: `php artisan migrate --force`.
- Seed minimal reference data if needed: `php artisan db:seed --force` (uses `DatabaseSeeder`; production may prefer targeted seeders).
- Build frontend: `npm ci && npm run build`.

## Security checklist
- MFA for privileged roles: set `SECURITY_ADMIN_MFA_REQUIRED=true` in production/staging. Ensure staff enroll TOTP (`/api/v1/auth/two-factor/*`).
- Rate limits: tune `RATE_LIMIT_AUTH|WEBHOOKS|MONEY|ADMIN` per load. 429 includes `Retry-After`.
- Sessions encrypted (`SESSION_ENCRYPT=true`); use Redis-backed sessions/queues.
- Redaction: logs and audit records mask secrets (configured in `config/security.php`).
- Webhooks: verify provider signatures, respect idempotency, treat unknown outcomes as `reconciling`.
- Admin endpoints protected by Sanctum + role permissions + `RequireTwoFactor` (when enabled).
- Dependency audit: run `composer audit --locked` in CI (network required). Address critical/high advisories.
- DB timeouts: set `DB_STATEMENT_TIMEOUT` and `DB_IDLE_TRANSACTION_TIMEOUT` (ms) to prevent long-held locks. Monitor.

## Reliability & operations
- Transactions: `ResilientTransaction::run` retries transient deadlocks/serialization failures (40001/40P01) up to 3 attempts. Keep financial operations in one transaction.
- Provider timeout: `WithdrawalService` marks withdrawals `reconciling` on provider timeout (`PROVIDER_TIMEOUT`) and leaves holds intact; reconciled via `reconciliation:run --layer=provider`.
- Outbox: `outbox:process` runs `everyMinute()->withoutOverlapping()`. Worker must be long-running; processor reclaims messages stuck in `processing` after timeout. Consumers must be idempotent (at-least-once).
- Realtime notifications: recording a notification queues a broadcast job on the queue connection; if Reverb is down the in-app notification is still persisted and delivered by REST polling, so push failures are non-fatal. Channel auth uses the Sanctum bearer token (`/broadcasting/auth`).
- Scheduled jobs: `reconciliation:run --layer=wallet|provider|settlement` (daily) and `reconciliation:alert` (daily). `partitions:ensure` should run before month boundary (cron daily is sufficient to pre-create future months).
- Reconciliation: read-only; never auto-correct. Discrepancies require permissioned resolution (`reconciliation.resolve`, `ledger.correct`) with audit trail.
- Backups: automate PostgreSQL logical/physical backups (e.g. `pg_dump` + WAL archiving). Store off-site with retention policy. Test restore quarterly on isolated env.
- Monitoring/alerts: track queue depth (failed jobs), outbox pending/failed counts, reconciliation runs with discrepancies, long-running queries (timeouts help), Redis connectivity, rate-limit 429 spikes, webhook failures. Alert on `reconciliation:alert` output or unresolved items.
- Incident runbook (high level):
  1. Contain: freeze affected wallets (`admin.freeze`), pause non-essential jobs if needed.
  2. Assess: inspect reconciliation items (`GET /api/v1/admin/reconciliation/items`), audit events, logs (redacted), provider status.
  3. Recover: use `ledger.correct` (reverse) only with approvals; for timeouts, let provider reconciliation determine outcome. Do not mutate posted journals directly.
  4. Verify: run targeted reconciliation, check balances (`wallet_balances` vs `ledger_postings`), verify invariants.
  5. Post-incident: document root cause, fixes, and preventive actions.

## Deployment checklist
- [ ] Backup current DB (and testable snapshot) before release.
- [ ] Run `composer install --no-dev`, `npm run build`, cache config/routes/views.
- [ ] `php artisan migrate --force` (review migrations in PR).
- [ ] Restart queue workers and scheduler (and the Reverb server if realtime is enabled).
- [ ] Smoke tests: auth, small transfer, deposit intent/webhook path (provider fake), withdrawal reserve/initiate. Check 429 headers, idempotency, error envelopes.
- [ ] Verify `outbox:process`, reconciliation commands, partitions:ensure run in prod schedule.
- [ ] Enable MFA in prod if required; confirm admin users enrolled.
- [ ] Post-deploy: monitor logs, queues, reconciliation alert, DB slow queries.
