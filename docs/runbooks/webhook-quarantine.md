# Runbook: Webhook Quarantine

Trigger: alert `webhook_quarantine`; a provider payload was rejected or parked.

## Symptoms
- `webhook_inbox`/`payment_events` rows in quarantined state; deposit remains unpaid;
  alert fired.

## Safe first step
1. Read the quarantine row: provider, event_id, signature, payload hash, rejection
  reason (signature mismatch, duplicate event_id, amount/currency/reference mismatch).
2. Do **not** credit the wallet manually. Funds enter only through the verified path.

## Investigate
- Signature mismatch → likely provider credential/signing key drift or malicious
  attempt (treat as fraud signal per risk R-04).
- Duplicate `event_id` → worker replay; confirm the original succeeded and ignore.
- Amount/currency/reference mismatch → provider vs system data drift; reconcile with
  the provider portal first.

## Fix
- Rotate/verify the signing secret and reseed the provider adapter for signature issues.
- For legitimate duplicates, re-run the consumer (idempotent by event_id).
- For genuine events with matching data, re-verify the payload hash and process.

## Confirmation
- Deposit reaches `succeeded`; wallet balance increased exactly once; as recent — the
  payment_events row is `accepted` with a matching ledger posting.

## Postmortem
- Any manual action outside the verified path requires a sign-off and an audit row.