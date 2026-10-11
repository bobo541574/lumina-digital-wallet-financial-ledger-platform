# Backend Architecture & Behaviour

The backend is a modular Laravel application (PHP 8.4, PostgreSQL, Redis). Money is
always handled in integer minor units (ADR-0001); balance mutations are serialised by
deterministic row locks (ADR-0003).

## Modules and ownership

| Module | Purpose |
| ------ | ------- |
| `Identity` | Users, auth (Sanctum), 2FA, roles/permissions, rate limiting. |
| `Wallets` | Wallet lifecycle, instantiation, admin operations. |
| `Transfers` | P2P transfers with atomic debit/credit. |
| `Deposits` | Top-up via providers; webhook ingestion; verify path. |
| `Withdrawals` | Payouts with hold/reserved balance; provider payouts. |
| `PaymentProviders` | Adapter layer over KBZPay, WaveMoney, AYA, 2C2P (fakes in dev/test). |
| `Ledger` | Double-entry journal, posting service, reversals, balances. |
| `Reconciliation` | Wallet/provider reconciliation and resolution. |
| `PaymentEvents` | Partitioned event provenance store. |
| `Outbox` | Transactional outbox publisher/worker. |
| `Audit` | Immutable audit log of every state transition. |
| `Shared` | Enums, exceptions, error codes, idempotency, money/currency helpers. |

## Cross-cutting invariants (violating them fails CI)

1. **No float money anywhere.** Migrations/casts/API use integers; `MigrationConventionsTest` scans migrations.
2. **Ledger entries are immutable.** DB triggers deny update/delete; corrections are reversals (ADR-0006).
3. **Every money movement is idempotent** at the API and webhook layer.
4. **Every customer-scoped query hides existence** (404 for other customers' wallets/deposits).
5. **Every financial transition persists an audit row** and, where external effect exists, an outbox row.
6. **OpenAPI must match the route table.** `tests/Unit/OpenApiContractTest.php` fails the build on drift.

## URL, status, and error conventions

See `docs/architecture/overview.md` (diagrams, error envelope, URL structure) and
`docs/architecture/api/error-codes.md` (stable machine-readable codes).

## Verification entry points

Run `php artisan test --compact` for the suite. Granular runs:
`vendor/bin/pest tests/Feature/Ledger --compact`, `tests/Feature/Transfers`, etc.
Detailed strategy: `docs/testing/testing-strategy.md`.