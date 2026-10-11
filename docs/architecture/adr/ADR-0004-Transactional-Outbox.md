# ADR-0004 — Transactional outbox for cross-boundary side effects

Status: Accepted (2026-10-10)
Context: Posting a ledger entry and then notifying/externalizing must not split a
transaction (risk R-08).
Decision: Side effects that must not be lost (webhooks to providers, audit pushes,
long-running integrity work) are written to an **outbox table inside the same
transaction** as the money movement. A worker (`outbox:process`) polls with retry and
reclaim; consumers are idempotent because the outbox row key is the unique outcome of
the transaction.
Consequences: at-least-once delivery; consumers must be idempotent (they are).
Enforced by: `tests/Feature/Outbox/OutboxTest.php`, `tests/Unit/Outbox/OutboxServiceTest.php`.
References: FR-701, FR-702.