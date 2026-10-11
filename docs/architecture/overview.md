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
├── Admin/             # cross-module admin search & dashboards (Phase 06)
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
├── Policies/          # ownership/authorization policies
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
  Codes and HTTP mappings: `docs/architecture/api/error-codes.md`.
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

### Delete behavior — database constraints vs code

Two different concerns must not be mixed:

- **Database constraints decide what may or may not be deleted (integrity).** They are
  unconditional: raw SQL, queued jobs, console commands, and admin tooling all go
  through the same foreign keys, so a rule cannot be bypassed or forgotten.
- **Code decides when and how a deletion happens (workflow).** Soft-delete,
  deactivation, reversals, conditional checks, and audit logging live in services —
  the database has no way to express them.

Decision rule:

| Relationship | DB level | Example in this repo |
| :--- | :--- | :--- |
| Owned technical rows (pivots, tokens, derived caches) | `cascadeOnDelete` | `permission_role`, `role_user`, `permission_user`, `wallet_balances` — meaningless without the parent; cleaned atomically in the same statement |
| Financial / immutable rows | `restrictOnDelete` | `wallets.user_id`, `deposits.*`, `withdrawals.*`, `transfers.*`, `ledger_postings.*`, `wallet_holds.wallet_id` — append-only money facts must never disappear |
| Optional back-refs / audit pointers | `nullOnDelete` | `created_by`, `approved_by`, `confirmed_by`, `actor_id`, `requested_by`, `processed_by`, `journal_entry_id` — the row survives, only the pointer is dropped |

Cascade belongs at the database level even for technical rows because `ON DELETE
CASCADE` removes parent + children in one transactional statement — no partial
deletes, no race window — whereas re-implementing it in Eloquent events introduces
a second, out-of-band source of truth.

Money-touching tables must never cascade. Deleting a user along with their wallets,
deposits and journal history would destroy the audit trail and the balance story;
instead, account closure is a code-level decision (soft flag / deactivation) and
any money movement is reversed with a contra-posting, never deleted.

Complementarity: the database blocks what must not be deleted; the service layer
owns the business decision. Service or model events are a complement for audit and
workflow and are never the only integrity guarantee.

### Column & reference conventions

- **UUIDs live in `uuid` columns only.** Never store a UUID in a `string`/`varchar`
  column. Provider/tracing identifiers that originate outside the platform are the
  sole exception and use `string`: `event_id`, `provider_transaction_id`,
  `correlation_id`.
- **Polymorphic relations use the morph helpers**: `uuidMorphs('source')` /
  `nullableUuidMorphs('actor')` — never a hand-rolled `string('..._type')` +
  `string('..._id')` pair. The generated `_id` column is `uuid` and the `_type`
  column is `varchar`.
- **Database constraints are the source of truth**; model events and services are
  complementary (audit, workflows) and must never be the only integrity guarantee.
  Constraints hold for raw SQL, queued jobs, and other services.
- These rules are enforced by `tests/Unit/MigrationConventionsTest.php`; new
  migrations that break them fail the suite.

## Payment providers

`App\Modules\PaymentProviders` owns the outbound gateway adapters and the signed
webhook pipeline. Integration happens only through `ProviderManager`, which both
`DepositService` (collect) and `WithdrawalService` (payout) receive; the module
is not registered as a Laravel service provider because every adapter is
resolved explicitly from `config/payment.php`.

### Adapters

- `CollectableProvider::createPayment(CollectRequest)` opens a pay-in session and
  returns a normalized `PaymentIntentResult` (`payment_url`, provider ids, and a
  `PaymentStatus` of `processing`/`succeeded`/`failed`).
- `PaymentProviderInterface::verifySignature()` / `parseEvent()` power the webhook
  inbox: verify → dedupe on `(provider, event_id)` → verify amount/currency →
  post atomically (see `docs/business/transaction-state-machines.md`).
- Real providers (`kbzpay`, `wavemoney`, `aya`, `2c2p`) are config-driven. Their
  **payout** adapters are config-gated: they throw `ProviderUnavailableException`
  until a `payout.endpoint` is configured, because disbursement field names must
  be confirmed against each merchant's agreement before going live.
- Settlement is **synchronous** when the provider reports it (`succeeded` on
  `createPayment`, e.g. AYA push), otherwise **asynchronous** via webhook.
- AYA does not sign callbacks: `verifySignature()` is always `false`, so AYA
  settlement comes from a synchronous result or `checkRequestPayment`/reconcilation,
  never from an unsigned webhook.

### Webhooks

The customer-facing callback route is
`POST /api/v1/payment-providers/{provider}/webhook` (named
`payment-providers.webhook`, used as the `callbackUrl` for collect intents);
payout callbacks land on `.../{provider}/payout-webhook`. Both verify signatures
before doing anything, so they can sit outside the session/auth middleware.

## Events, outbox & partitioning

Financial writes do not publish side effects inline. Instead, an outbox message
is inserted **inside the same transaction** as the ledger write, so either the
money movement and its event are both committed or neither is. This closes the
gap between "the database committed" and "the world was told".

### Transactional outbox (`App\Modules\Outbox`)

- `OutboxService::record(...)` writes a `pending` message and asserts it is called
  from inside a transaction (`OutboxNotTransactionalException` otherwise).
- `TransferService`, `DepositService` and `WithdrawalService` record
  `transfer.completed`, `deposit.succeeded`, `withdrawal.succeeded` /
  `withdrawal.failed` from within their existing transaction boundaries.
- `OutboxProcessor::process()` claims pending messages with
  `FOR UPDATE SKIP LOCKED` (safe for concurrent workers), marks them `processing`,
  dispatches a `DomainEvent`, then marks them `processed`.
- Failures retry with backoff (`config/outbox.php`); after `max_attempts` the
  message is parked as `failed` for reconciliation. Messages left `processing`
  by a crashed worker are reclaimed after `processing_timeout` seconds, so
  delivery resumes after a restart.
- Delivery is **at-least-once**: consumers must be idempotent. Run the worker
  with `php artisan outbox:process`.

### Monthly partitions (`App\Modules\PaymentEvents`)

- `payment_events` is an append-only, high-volume event log, partitioned monthly
  by `RANGE (occurred_at)`. The primary key is `(id, occurred_at)` because
  PostgreSQL requires the partition key in every primary key; ids stay globally
  unique by UUID convention.
- A `payment_events_default` partition guarantees writes never fail, even for a
  month with no partition yet.
- `PartitionManager::ensure()` pre-creates the current month plus N future
  months, named `<table>_<YYYY>_<MM>`, idempotently, and moves any rows out of
  the default partition before attaching a new range.
- Partition-aware indexes are declared on the parent and inherited by every
  partition. Timestamp-range queries prune unrelated partitions.
- `php artisan partitions:ensure` pre-creates partitions; `partitions:ensure
  --check` reports missing months (non-zero exit) for health monitoring.

## Reconciliation & financial recovery

Reconciliation is **read-only and non-correcting**: it detects and records
discrepancies, but never auto-adjusts a balance or ledger entry. Corrections are
explicit, permissioned and audited.

### Layers (`App\Modules\Reconciliation`)

- **Wallet** (`WalletReconciliationService`): recomputes each wallet's posted
  balance from `ledger_postings` (signed by account normal balance) and its
  reserved balance from active `wallet_holds`, then compares both against the
  cached `wallet_balances` projection.
- **Provider** (`ProviderReconciliationService::reconcileTransactions`): matches
  each `provider_settlement_line` to our deposits/withdrawals by
  `provider_transaction_id` (falling back to `reference`) and classifies every
  difference as `missing`, `duplicate`, `amount_mismatch` or `status_mismatch`.
- **Settlement** (`ProviderReconciliationService::reconcileSettlements`): checks a
  settlement's declared net total against the sum of its lines.

### Runs and items

- `ReconciliationRunner` creates a `reconciliation_runs` row per layer with
  `status` `running → completed | completed_with_discrepancies | failed`; a failed
  run captures the error rather than throwing.
- Every difference is a `reconciliation_items` row (`open` by default) carrying the
  layer, classification, subject, `expected`/`actual` snapshots and a signed
  `difference`.
- `ReconciliationResolutionService::resolve()` marks an item `resolved`/`ignored`
  exactly once (double-resolution → `RECONCILIATION_ITEM_ALREADY_RESOLVED`) and
  writes an `audit_events` record.
- `php artisan reconciliation:run [--layer=wallet|provider|settlement|all]`;
  `php artisan reconciliation:alert` reports unresolved discrepancies.

### Correction (journal reversal)

`LedgerReversalService` posts an equal-and-opposite `reversal` journal that offsets
the original exactly (never mutating it) and records a `journal.reversed` outbox
event. A journal may be reversed at most once (`REVERSAL_ALREADY_EXISTS`), and a
reversal itself cannot be reversed (`JOURNAL_NOT_REVERSIBLE`).

### Admin endpoints (`/api/v1/admin`, Sanctum)

| Method & path                                         | Permission          |
| ----------------------------------------------------- | ------------------- |
| `GET  reconciliation/runs`, `runs/{run}`, `items`     | `reconciliation.view` |
| `POST reconciliation/run`                             | `reconciliation.run`  |
| `POST reconciliation/items/{item}/resolve`            | `reconciliation.run`  |
| `POST journal-entries/{journalEntry}/reverse`         | `ledger.correct`      |

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
