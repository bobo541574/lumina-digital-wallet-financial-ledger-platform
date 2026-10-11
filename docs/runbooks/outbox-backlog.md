# Runbook: Outbox Backlog

Trigger: alert `outbox_backlog`; the outbox worker is beyond its lag threshold; provider
callbacks/notifications are delayed.

## Symptoms
- Growing `outbox` table; consumer latency alert; external notifications delayed.

## Safe first step
1. Inspect the failing outbox rows (consumer error column / last_attempt).
2. Confirm the DB transaction that produced them committed cleanly (outbox rows exist
  only with their source transaction — ADR-0004).

## Investigate
- Rate-limit on the downstream consumer (provider API throttle).
- A poisoned row repeatedly failing (malformed payload).

## Fix
- Scale the worker horizontally safely: outbox processing is idempotent and reclaimable.
- For a poisoned row, correct the payload and requeue, or discard **only** after
  confirming the source transaction's side effect is already satisfied elsewhere.

## Confirmation
- Backlog drains below threshold; every processed row has a `completed` state; no
  duplicate external effect (consumer idempotency).

## Postmortem
- If throttled by a provider, add backoff; record in risk register R-08.