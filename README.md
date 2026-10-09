# Lumina — Digital Wallet & Financial Ledger Platform

A production-oriented digital wallet built on a **double-entry ledger** with cached
balances, multi-currency wallets, atomic internal transfers, deposits, withdrawals with
holds, idempotency, reconciliation, and PostgreSQL partitioning.

Stack: Laravel 13 · PostgreSQL 16 · Redis · Pest · TypeScript-ready API.

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
./vendor/bin/pint       # Code style
```

## Documentation

| Document                             | Contents                                    |
| ------------------------------------ | ------------------------------------------- |
| `docs/business-rules.md`             | Actors, currency/wallet/idempotency rules   |
| `docs/transaction-state-machines.md` | Transfer/deposit/withdrawal/hold lifecycles |
| `docs/accounting-rules.md`           | Chart of accounts, posting rules, reversal  |
| `docs/api-error-codes.md`            | Error envelope and code catalog             |
| `docs/architecture.md`               | Modules, layering, conventions              |

## Status

- [x] Phase 00 — Requirements & financial rules
- [x] Phase 01 — Project foundation (auth, modules, CI, tests)
- [x] Phase 02 — Database & ledger core
- [ ] Phase 03 — Wallet balance & internal transfer
- [ ] Phase 04 — Deposits
- [ ] Phase 05 — Withdrawals
- [ ] Phase 06+ — History, outbox, reconciliation, security, deployment
