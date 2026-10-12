# Lumina — Digital Wallet & Financial Ledger Platform

A production-oriented digital wallet built on a **double-entry ledger** with cached balances, multi-currency wallets, atomic internal transfers, deposits, withdrawals with holds, idempotency, reconciliation, and PostgreSQL partitioning.

Stack: Laravel 13 · PHP 8.4 · PostgreSQL 16 · Redis · Pest · Vue 3 + TypeScript + Vite + Pinia · Playwright + Vitest.

Realtime notifications via Laravel Reverb (WebSockets) with REST polling fallback.

## Quick start

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate

createdb lumina
createdb lumina_test

php artisan migrate --seed
php artisan serve
npm run dev
```

### Demo credentials (seeded)

After `php artisan migrate --seed`, the following accounts are available:

| Role | Email | Password |
|---|---|---|
| Customer | `test@example.com` | `password` |
| Admin | `admin@example.com` | `password` |
| Finance Operator | `finance@example.com` | `password` |
| Auditor | `auditor@example.com` | `password` |
| Demo Users | `demo1@example.com`, `demo2@example.com`, `demo3@example.com` | `password` |

Demo data is **ledger-backed** (balances derive from journal postings), includes transfers, withdrawals in varied states, provider/manual deposits, holds, audit events, notifications, and processed outbox messages.

### Realtime notifications (optional)

Notifications work with REST polling alone. To enable WebSocket push:

```sh
php artisan reverb:start   # WebSocket server (default 0.0.0.0:8080)
php artisan queue:work     # process broadcast jobs
```

Set `BROADCAST_CONNECTION=reverb` and `REVERB_*` (which map to `VITE_REVERB_*`) in `.env`. Without Reverb configured, the SPA never opens a socket and falls back to polling.

## Quality gates

```sh
php artisan test        # Pest test suite (173 tests, all passing)
./vendor/bin/pint       # Auto-fix code style
./vendor/bin/pint --test # Check code style
npm run test:unit       # Vitest frontend unit/component tests
npm run typecheck       # vue-tsc type check
npm run lint            # ESLint
npm run e2e             # Playwright browser journeys + axe a11y scans
npm run types:api       # Regenerate frontend types from OpenAPI contract
composer bench          # Performance benchmarks (manual)
```

## Documentation

- Implementation plan: [`BLUEPRINT.md`](BLUEPRINT.md)
- Full docs index: [`docs/README.md`](docs/README.md)
- Business flow: [`docs/business/business-flow.md`](docs/business/business-flow.md)

### Key docs

| Document | Purpose |
|---|---|
| `docs/business/business-flow.md` | End-to-end business flows (wallets, deposits, transfers, withdrawals, ledger, reconciliation) |
| `docs/business/accounting-rules.md` | Double-entry rules, chart of accounts, postings, reversals |
| `docs/business/transaction-state-machines.md` | Transfer/deposit/withdrawal/hold state transitions |
| `docs/business/permissions-matrix.md` | Role × permission matrix |
| `docs/architecture/api/openapi.yaml` | OpenAPI 3.1 contract (44 paths, CI-validated) |
| `docs/architecture/overview.md` | Modules, layering, invariants |
| `docs/compliance/standards-mapping.md` | ISO alignment (12207, 29148, 25010, 27001, 9241-210, 40500/WCAG 2.2) |
| `docs/operations/deployment.md` | Deployment, ops, runbooks |
| `docs/testing/testing-strategy.md` | Test tiers, money-movement rules, concurrency, E2E |

## Security & reliability

- **Double-entry ledger** as financial source of truth; cached balances reconciled against postings.
- **RBAC** with ownership enforcement; admin MFA configurable via `security.two_factor.admin_required`.
- **Atomicity**: ledger postings + wallet balance projections + state changes inside resilient transactions.
- **Idempotency** on write endpoints (Idempotency-Key + request hash).
- **Withdrawals**: full-amount reservation (amount+fee) via holds; timeout/provider errors move to reconciliation (never auto-release on unverified failure).
- **Provider webhooks**: HMAC signature verification, inbox deduplication, amount/currency checks, quarantine on mismatch.
- **Transactional outbox**: domain events committed with state changes; delivered at-least-once with retries/backoff; notifications created only after commit.
- **Reconciliation**: wallet (Layer 1), provider/settlement (Layer 2/3); discrepancies recorded, never auto-corrected.
- **Rate limiting**, sensitive data redaction, DB statement/idle transaction timeouts, and structured audit logging.

## Architecture

- **Modular monolith** (feature modules under `app/Modules/*`) with clear boundaries.
- **PostgreSQL** for relational integrity; **Redis** for caching/queues.
- **Laravel Reverb** for realtime; **Pinia + Vue Router** for SPA state/routing; **shadcn-vue/Tailwind** UI.
- **Partitioning**: payment_events partitioned by month; hybrid approach for large tables.

## CI

See [`.github/workflows/ci.yml`](.github/workflows/ci.yml) for gates (Pest, Pint, frontend lint/typecheck/unit, dependency audit, OpenAPI validation).

## E2E

E2E suite boots a throwaway DB (`lumina_e2e`), runs migrations+seed, builds assets, serves via `php artisan serve`. Smoke tests can run against any environment via `E2E_BASE_URL`.

All tests pass: 173/173 feature/unit tests with 646 assertions.
