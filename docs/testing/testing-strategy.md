# Testing Strategy

## Stack & commands

Pest on PHPUnit. Run entire suite: `php artisan test --compact`. Direct runner:
`vendor/bin/pest`. Filter: `vendor/bin/pest tests/Feature/Transfers --compact`,
`vendor/bin/pest --filter=some_test`.

Frontend: `npm run test:unit` (Vitest), `npm run typecheck` (vue-tsc), `npm run lint`
(ESLint), and `npm run e2e` (Playwright). The E2E runner boots a throwaway instance via
`e2e/server.sh` (fresh `lumina_e2e` database, demo seed, built assets) and runs browser
journeys plus `@axe-core/playwright` accessibility scans; point it at an existing server
by setting `E2E_BASE_URL`.

## Test categories

| Tier | Location | Covers | Run in CI |
| ---- | -------- | ------ | --------- |
| Feature | `tests/Feature/` | HTTP workflows end to end (auth → wallet → transfer/deposit/withdraw → reconciliation) | yes |
| Unit | `tests/Unit/` | Services, enums, idempotency, outbox, partitioning, OpenAPI contract, migration conventions | yes |
| Concurrency | `tests/Concurrency/` | Overspend/race safety against the same rows in forked workers | yes |
| Performance | `tests/Performance/` | Benchmark harness (partitioned vs non-partitioned; bulk ingestion) | manual |
| Frontend unit | `resources/js/**/*.spec.ts` | Components, stores, guards, formatting (Vitest + jsdom) | yes |
| E2E | `e2e/` | Browser journeys + WCAG 2.2 AA axe scans (Playwright) | yes |

## Money-movement test rules (highest risk area)

1. **Assert both legs.** A transfer test checks debit, credit, and journal balance
   (`sum(posted) == 0` per currency).
2. **Assert idempotency.** Replaying the same key/`event_id` must be a no-op with the
   same result.
3. **Assert the failure mode that matters (no lost funds, no double movement).**
   Concurrency overspend tests assert `available >= 0` and total settled == funded.
4. **Assert state machine transitions** using the tables in
   `docs/business/transaction-state-machines.md` (including `timeout → reconciling`).

## Concurrency testing approach

`tests/Concurrency/` forks N worker processes (`pcntl`) that exercise the same wallet
through the real service layer against `SELECT … FOR UPDATE` (ADR-0003). The assertion
is the invariant, not an HTTP status:

- exactly one of N equal requests may succeed; every other worker fails cleanly with
  `INSUFFICIENT_FUNDS`;
- no balance goes negative, no reservation leaks, funds/ledger stay balanced.

These tests run in a dedicated, **non-transactional** Pest suite because forked workers
must see committed rows. They require the `pcntl` extension and skip when absent, so CI
(Linux) is the authoritative run. This is the definitive regression net for R-02
(overspend).

## Contract & structural gates

- `tests/Unit/OpenApiContractTest.php` — every documented path registered and vice versa.
- `tests/Unit/MigrationConventionsTest.php` — no floats, required conventions.
- `tests/Feature/Database/DatabaseConstraintTest.php` — financial invariants are enforced
  at the database layer (non-negative balances, positive amounts, distinct wallets,
  ledger immutability triggers).
- Partition/outbox/idempotency tests protect the OPS-001/FIN-002 examples.

## Evidence & comprehensive run

After any feature branch:
1. `vendor/bin/pint --dirty --format agent`
2. `vendor/bin/pest` (affected areas at minimum)
3. On green, run the complete `php artisan test --compact` and record the result in
   `docs/compliance/review-evidence/`.

## Test data

Use model factories and database transactions (`RefreshDatabase`) for isolation;
providers run as fakes (`PaymentProviders`) unless a specific adapter contract is under
test.