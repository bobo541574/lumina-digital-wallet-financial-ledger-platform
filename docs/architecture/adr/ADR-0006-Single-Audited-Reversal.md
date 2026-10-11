# ADR-0006 — Single, audited reversal (no editing of posted entries)

Status: Accepted (2026-10-10)
Context: Immutable ledger vs. operational correction need (risks R-06, R-07).
Decision: Posted ledger entries are **not updateable/deletable** (enforced by triggers).
Corrections are made by posting a new, opposite entry via `LedgerReversalService` that:
1. references the original entry;
2. is itself non-reversible (reversal of a reversal is rejected);
3. always carries a reason and audit trail.
Consequences: Ledger history remains a provable chain; accidental double-correcting is
blocked; reconciliation mismatch is resolved the same way.
Enforced by: `tests/Feature/Reconciliation/FinancialRecoveryTest.php`.
References: FR-606, FR-706.