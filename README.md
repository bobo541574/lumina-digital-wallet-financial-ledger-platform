# Lumina — Digital Wallet & Financial Ledger Platform

A production-oriented digital wallet built on a **double-entry ledger** with cached
balances, multi-currency wallets, atomic internal transfers, deposits, withdrawals
with holds, idempotency, reconciliation, and PostgreSQL partitioning.

Stack: Laravel 13 · PHP 8.4 · PostgreSQL 16 · Redis · Pest · TypeScript-ready API.
Realtime notification push is optional, via Laravel Reverb (WebSockets).

The implementation plan lives in [`BLUEPRINT.md`](BLUEPRINT.md). Financial rules,
state machines, accounting, and API error codes are documented under [`docs/`](docs).

## Getting started

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate

createdb lumina
createdb lumina_test

php artisan migrate --seed
php artisan serve
npm run dev        # frontend (frontend track)
```

### Realtime notifications (optional)

The notification center works with REST polling alone. To add WebSocket push,
set `BROADCAST_CONNECTION=reverb`, fill the `REVERB_*` app credentials (they flow
into `VITE_REVERB_*` for the frontend) in `.env`, then run the Reverb server and a
queue worker alongside `php artisan serve`:

```sh
php artisan reverb:start   # WebSocket server (default 0.0.0.0:8080)
php artisan queue:work     # sends the queued broadcast jobs
```

Without a Reverb key the frontend never opens a socket and polling continues.

## Quality gates

```sh
php artisan test        # Pest test suite (PostgreSQL)
./vendor/bin/pint --test # Code style (check)
./vendor/bin/pint       # Auto-fix
composer bench          # Performance benchmarks (excluded from default)
npm run test:unit       # Vitest frontend unit/component tests
npm run typecheck       # vue-tsc type check
npm run lint            # ESLint
npm run e2e             # Playwright browser journeys + axe a11y scans
npm run types:api       # Regenerate frontend types from the OpenAPI contract
```

## Documentation

Full index: `docs/README.md`. The implementation plan and phase numbering live in
[`BLUEPRINT.md`](BLUEPRINT.md).

| Document                                 | Contents                                    |
| ---------------------------------------- | ------------------------------------------- |
| `docs/requirements/*`                    | BR / FR / NFR / acceptance criteria          |
| `docs/business/business-rules.md`        | Actors, currency/wallet/idempotency rules   |
| `docs/business/transaction-state-machines.md` | Transfer/deposit/withdrawal/hold lifecycles |
| `docs/business/accounting-rules.md`      | Chart of accounts, posting rules, reversal  |
| `docs/business/permissions-matrix.md`    | Role × permission × endpoint matrix         |
| `docs/architecture/api/error-codes.md`   | Error envelope and code catalog             |
| `docs/architecture/api/openapi.yaml`     | OpenAPI 3.1 contract (CI-validated)         |
| `docs/architecture/overview.md`          | Modules, layering, conventions              |
| `docs/compliance/*`                      | Standards mapping, traceability, risk register |
| `docs/operations/deployment.md`          | Deployment, ops, runbooks, checklist        |

## Security & reliability

- RBAC with ownership enforcement; admin MFA configurable via `security.two_factor.admin_required`.
- Rate limits, webhook signature verification, and sensitive data redaction.
- Resilient transactions (concurrency retry), provider timeout recovery, scheduled outbox processing, and DB timeouts.
- Idempotency on write endpoints.

## CI

See [`.github/workflows/ci.yml`](.github/workflows/ci.yml) for the standard gates (tests, Pint, dependency audit).

## Status

Phases follow the [`BLUEPRINT.md`](BLUEPRINT.md) roadmap. Backend is complete through
the equivalent of the blueprint's build/ops phases. The frontend track foundation and
authentication UI (Phases 02–04) are implemented; remaining frontend work is portal
feature polish, tests/E2E and deploy config (Phases 06–14).

- [x] Phase 00 — Requirements, business rules & compliance (backing `docs/requirements`, `docs/compliance`)
- [x] Phase 01 — System architecture & API contract (incl. `docs/architecture/api/openapi.yaml`)
- [x] Phase 02 — Backend foundation (auth, modules, CI, tests)
- [x] Phase 05 — Database & Ledger Core
- [x] Phase 07 — Internal wallet transfer (backend)
- [x] Phase 08 — Deposits (backend)
- [x] Phase 09 — Withdrawals (backend)
- [x] Phase 10 — Transaction history, statements & Admin (backend)
- [x] Phase 11 — Events, outbox & PostgreSQL partitioning
- [x] Phase 12 — Reconciliation & recovery
- [x] Phase 13 — Backend security/reliability (QA evidence pending in `docs/compliance/review-evidence/`)
- [x] Phase 14 — Deployment & operations (docs)

- [x] Phase 02–03 — Frontend foundation (Vue 3 + TS + Vite + Pinia + router + tokens) + design-system primitives
- [x] Phase 04 — Portal authentication/RBAC UI (login, register, MFA, route guards)
- [x] Phase 06–10 — Customer & Admin portal screens (UX polish complete: loading/retry states, mobile nav, dialog & tab accessibility)
- [x] Phase 13 — Frontend tests, accessibility, E2E
- [x] Phase 14 — Frontend production build & deploy config (CI gate, security headers, caching, smoke tests)
