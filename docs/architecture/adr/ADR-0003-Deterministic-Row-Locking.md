# ADR-0003 — Deterministic row locking for balance-critical operations

Status: Accepted (2026-10-10)
Context: Concurrent withdrawals/transfers must not overspend (risk R-02). Flimsy
`Transaction::atomic()` without row locks allows lost updates.
Decision: Balance-mutating workflows:
1. Open one transaction, acquire `SELECT … FOR UPDATE` on all affected wallet rows in
   **deterministic order** (ascending `wallet_id`);
2. Re-read balance from the locked rows, verify availability;
3. Apply journal + balances, commit before releasing locks.
No conditional writes (`UPDATE … WHERE balance >= x`) as the sole guard.
Consequences: Two-sided operations can deadlock-free; short critical sections; the
`ResilientTransaction` wrapper centralises lock ordering.
Enforced by: `tests/Concurrency/TransferConcurrencyTest.php` and
`tests/Concurrency/WithdrawalConcurrencyTest.php` (forked workers; only one of N equal
requests may win, the rest fail cleanly).
References: BR-018, FR-303, FR-306.