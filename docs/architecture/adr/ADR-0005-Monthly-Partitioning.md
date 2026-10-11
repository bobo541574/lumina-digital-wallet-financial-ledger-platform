# ADR-0005 — Monthly table partitioning for high-volume event data

Status: Accepted (2026-10-10)
Context: Provider payload and payment-event tables grow monotonically; a single table
degenerates under the complementary lookups of reconciliation (risk R-12).
Decision: `payment_events` (and the payment-event partition) are monthly-partitioned by
`event_at`; a **default partition** accepts strays; `PaymentEvents:ensure` runs daily to
create the next partition.
Enforced by: `partitions:ensure` command, `tests/Feature/Partitioning/PartitionTest.php`.
References: FR-703.