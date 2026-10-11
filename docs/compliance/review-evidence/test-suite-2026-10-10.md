# Evidence — Full Test Suite (2026-10-10)

| Field | Value |
| ----- | ----- |
| Date | 2026-10-10 |
| Revision | `eb144b5` + uncommitted working tree |
| Command | `php artisan test --compact` |
| Result | **146 passed**, 525 assertions, ~6.6 s (1 non-blocking warning) |
| Environment | PHP 8.4.13; PostgreSQL 16.10; Laravel 13.35.0; DB `lumina_test` @ 127.0.0.1:5432 |

```
Tests:    146 passed (525 assertions)
Duration: ~6.6s
```

## Coverage of the (ခ) verification batch

| Area | Suite / file | Status |
| ---- | ------------ | ------ |
| Transfer overspend race | `tests/Concurrency/TransferConcurrencyTest.php` (2) | Pass |
| Withdrawal overspend race | `tests/Concurrency/WithdrawalConcurrencyTest.php` (1) | Pass |
| Financial DB constraints + ledger immutability | `tests/Feature/Database/DatabaseConstraintTest.php` (7) | Pass |
| OpenAPI ↔ route contract | `tests/Unit/OpenApiContractTest.php` (3) | Pass |
| Partitioning | `tests/Feature/Partitioning/PartitionTest.php` | Pass |

## Notes

- The dedicated `Concurrency` suite forks `pcntl` workers and therefore runs
  **non-transactionally** (committed rows are required). CI (Linux) is the
  authoritative environment; the suite skips where `pcntl` is unavailable.
- The Performance suite (`composer bench`) is intentionally excluded from this run;
  see `partitioning-benchmark.md`.