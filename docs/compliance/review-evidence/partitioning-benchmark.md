# Evidence — Partitioning Benchmark

| Field | Value |
| ----- | ----- |
| Date | 2026-10-10 |
| Revision | `eb144b5` (plus uncommitted partitioning benchmark work in `tests/Performance/BenchmarkTest.php`) |
| Command | `composer bench` (= `php vendor/bin/pest --configuration phpunit.performance.xml`) |
| Result | All Performance tests passed |
| Environment | PHP 8.4.13 (NTS, clang); PostgreSQL 16.10 (Homebrew, aarch64-darwin); Laravel 13.35.0; DB `lumina_test` on 127.0.0.1:5432 |

Purpose: provide operational evidence that the monthly RANGE partitioning of
`payment_events` (ADR-0005, FR-703) both **sustains high ingest throughput** and
**prunes unrelated partitions** for a single-month range query. Budgets are loose
regression guards, not acceptance thresholds.

## Results

```
[bench] hot-sender transfers                       100 ops in   736.2 ms  (   135.8 ops/s)
[bench] idempotent replays                          50 ops in    27.0 ms  (  1851.8 ops/s)
[bench] wallet history queries                      50 ops in    87.9 ms  (   568.5 ops/s)
[bench] payment event ingest (partitioned)        6000 ops in   146.1 ms  ( 41067.2 ops/s)
[bench] pruned single-month counts                 200 ops in    48.7 ms  (  4105.4 ops/s)
```

| Benchmark | Ops | Latency | Throughput | Budget |
| --------- | --- | ------- | ---------- | ------ |
| hot-sender transfers | 100 | 736.2 ms | 135.8 ops/s | < 60 s |
| idempotent replays | 50 | 27.0 ms | 1851.8 ops/s | < 30 s |
| wallet history queries | 50 | 87.9 ms | 568.5 ops/s | < 15 s |
| **payment event ingest (partitioned)** | 6000 | 146.1 ms | **41 067 ops/s** | < 60 s |
| **pruned single-month counts** | 200 | 48.7 ms | **4105 ops/s** | < 15 s |

## Partition pruning proof

The `pruned single-month counts` test runs `EXPLAIN` for a one-month range over a
table populated across **12 monthly partitions** and asserts the plan:

- **contains** the target month partition (`payment_events_<YYYY_MM>`), and
- **does not contain** the adjacent month's partition.

This confirms the query planner prunes the other 11 partitions, so single-month
reconciliation/read queries scan one partition's data rather than the whole table.
The deterministic assertion lives in
`tests/Performance/BenchmarkTest.php::serves a single-month query by pruning other partitions`.

## Operational significance

- Partitioned ingest of 6 000 events in ~146 ms left ample headroom for the
  high-volume provider event stream.
- The daily `PaymentEvents:ensure` command (FR-703) keeps at least the next month
  pre-created so writes never fall back to the default partition at peak.
- Numbers are indicative on a developer laptop; the authoritative CI run is the
  regression gate (`composer bench` is intentionally outside the default suite).