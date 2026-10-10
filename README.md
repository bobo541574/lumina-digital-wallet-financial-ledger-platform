# Lumina — Digital Wallet & Financial Ledger Platform

A production-oriented digital wallet built on a **double-entry ledger** with cached
balances, multi-currency wallets, atomic internal transfers, deposits, withdrawals
with holds, idempotency, reconciliation, and PostgreSQL partitioning.

Stack: Laravel 13 · PHP 8.4 · PostgreSQL 16 · Redis · Pest · TypeScript-ready API.

The implementation plan lives in [`BLUEPRINT.md`](BLUEPRINT.md). Financial rules,
state machines, accounting, and API error codes are documented under [`docs/`](docs).

## Getting started

```sh
composer install
cp .env.example .env
php artisan key:generate

createdb lumina
createdb lumina_test

php artisan migrate --seed
php artisan serve
```

## Quality gates

```sh
php artisan test        # Pest test suite (PostgreSQL)
./vendor/bin/pint --test # Code style (check)
./vendor/bin/pint       # Auto-fix
composer bench          # Performance benchmarks (excluded from default)
```

## Documentation

| Document                             | Contents                                    |
| ------------------------------------ | ------------------------------------------- |
| `docs/business-rules.md`             | Actors, currency/wallet/idempotency rules   |
| `docs/transaction-state-machines.md` | Transfer/deposit/withdrawal/hold lifecycles |
| `docs/accounting-rules.md`           | Chart of accounts, posting rules, reversal  |
| `docs/api-error-codes.md`            | Error envelope and code catalog             |
| `docs/architecture.md`               | Modules, layering, conventions              |

## Security & reliability

- RBAC with ownership enforcement; admin MFA configurable via `security.two_factor.admin_required`.
- Rate limits, webhook signature verification, and sensitive data redaction.
- Resilient transactions (concurrency retry), provider timeout recovery, scheduled outbox processing, and DB timeouts.
- Idempotency on write endpoints.

## CI

See [`.github/workflows/ci.yml`](.github/workflows/ci.yml) for the standard gates (tests, Pint, dependency audit).

## Status

- [x] Phase 00 — Requirements & financial rules
- [x] Phase 01 — Project foundation (auth, modules, CI, tests)
- [x] Phase 02 — Database & ledger core
- [x] Phase 03 — Wallet balance & internal transfer
- [x] Phase 04 — Deposits
- [x] Phase 05 — Withdrawals
- [x] Phase 06 — Transaction history, admin, audit
- [x] Phase 07 — Outbox & webhooks
- [x] Phase 08 — Reconciliation
- [x] Phase 09 — Security, reliability & performance (core)
- [ ] Phase 10 — Deployment & operations
