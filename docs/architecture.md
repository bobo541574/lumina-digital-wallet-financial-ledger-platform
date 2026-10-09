# Architecture & Conventions

Digital Wallet & Financial Ledger Platform

## Stack

- Laravel 13, PHP 8.4
- PostgreSQL 16 (source of truth)
- Redis (cache, queues, sessions)
- Pest for testing, Pint for style
- Sanctum for API tokens; in-house roles/permissions (`HasRoles` trait + Gate)

## Modular monolith

Domain code lives under `app/Modules`, one namespace per bounded context:

```
app/Modules/
├── Identity/          # users, roles, permissions, auth
├── Wallets/           # wallets, cached balances
├── Ledger/            # chart of accounts, journals, postings, posting service
├── Transfers/         # internal wallet-to-wallet transfers (Phase 03)
├── Deposits/          # manual + provider deposits (Phase 04)
├── Withdrawals/       # holds, payout state machine (Phase 05)
├── PaymentProviders/  # provider adapters, webhook inbox (Phase 04)
├── Reconciliation/    # ledger/balance/provider matching (Phase 08)
├── Audit/             # audit trail (Phase 06)
└── Shared/            # cross-cutting: errors, response envelope, currency
```

Each module uses the folder layout it needs:

```
Module/
├── Contracts/         # interfaces other modules depend on
├── DTOs/              # readonly value objects crossing layer boundaries
├── Enums/
├── Exceptions/
├── Http/              # Controllers, Requests, Resources, Middleware
├── Models/
├── Providers/
├── Results/
├── Services/          # domain rules live here
└── Database/          # Seeders, Factories
```

### Layering rule

`Controller → FormRequest → DTO → Service → Model`. Domain/financial rules live only
in Services (and models for invariants). Controllers and repositories must not contain
financial policy.

### Cross-module boundary

Financial modules never write `journal_entries`, `ledger_postings`, or cached balances
directly. They depend on `Ledger\Contracts\LedgerPostingServiceInterface`. The concrete
implementation is bound in `LedgerServiceProvider`.

## API conventions

- Routes are versioned under `/api/v1`, one file per module under `routes/modules`.
- Success envelope: `{ "data": ..., "meta": { "request_id": ... } }`.
- Error envelope: `{ "error": { "code", "message", "details", "request_id" } }`.
  Codes and HTTP mappings: `docs/api-error-codes.md`.
- Every request gets an `X-Request-Id` (propagated from the client if present),
  returned in the response header, and shared with the logging context.
- Mutating endpoints require an `Idempotency-Key` header (enforced from Phase 03+).

## Money rules

- All amounts are integers in currency minor units. Never floats.
- One currency per journal; enforced by composite foreign keys in the database.
- Posted journals/postings are append-only (model guards + PostgreSQL triggers).

## Identifiers & deletion

- **Primary key convention: UUID for every domain table** (`users`, `wallets`,
  `ledger_*`, `journal_entries`, `idempotency_keys`, `audit_events`, roles/permissions,
  and all future financial tables). Laravel-internal tables (`jobs`, `sessions`,
  `cache`, `personal_access_tokens`) keep their default auto-increment keys.
- Foreign keys follow the same rule; `personal_access_tokens.tokenable_id` is a UUID
  morph to match UUID users.
- **Delete behavior**: technical child rows (pivots, tokens, sessions) use
  `cascadeOnDelete`; financial relationships use `restrictOnDelete` (never cascade
  into money tables); nullable audit back-references (`created_by`, `actor_id`) use
  `nullOnDelete`. Business deletion logic lives in services (usually soft-delete or
  reversal), with the database constraint as the final safety net.

## Testing conventions

- Tests live in `tests/Unit` and `tests/Feature`, mirroring the module path.
- Feature tests use `RefreshDatabase` against PostgreSQL (`lumina_test`).
- Tests must assert financial invariants, not just HTTP status: balanced postings,
  no money movement on rejection, immutability, idempotency.
- Run with `php artisan test`; style with `./vendor/bin/pint --test`.

## Local setup

```sh
composer install
cp .env.example .env
php artisan key:generate
createdb lumina && createdb lumina_test
php artisan migrate --seed
php artisan test
```
