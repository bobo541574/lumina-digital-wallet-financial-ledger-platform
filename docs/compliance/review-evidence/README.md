# Review Evidence

**Do not** record a test result here unless it was actually executed in this repository,
with the command shown. On every merge, CI (`.github/workflows/ci.yml`) runs the suite;
this evidence directory exists for results that the pipeline does not automatically
capture (performance benchmarks, manual review sign-offs, DB constraint checks).

## Adding an entry

1. Is the result reproducible from a command in this repo? Record the exact command.
2. Record environment: PHP/PG/Redis versions from `composer show` / `.env`, date, DB state.
3. Link the code revision (`git rev-parse --short HEAD`).

## Index

| Date | Artefact | Command (exact) | Result | Link |
| ---- | -------- | --------------- | ------ | ---- |
| 2026-10-10 | Partitioning benchmark | `composer bench` | Pass (all Performance tests) | [partitioning-benchmark.md](partitioning-benchmark.md) |
| 2026-10-10 | Full test suite | `php artisan test --compact` | Pass (146 tests, 525 assertions) | [test-suite-2026-10-10.md](test-suite-2026-10-10.md) |
| 2026-10-10 | Financial DB constraints | `vendor/bin/pest tests/Feature/Database/DatabaseConstraintTest.php --compact` | Pass (7 tests, 27 assertions) | [db-constraints.md](db-constraints.md) |
| 2026-10-10 | Security controls verification | `php artisan test --compact` | Pass (controls exercised) | [security-controls.md](security-controls.md) |

*(Add rows above as evidence is produced — see `backend/testing-strategy.md` for which
artefacts the release gate requires.)*