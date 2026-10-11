# Runbook: Ledger Tampering Attempt

Trigger: alert `ledger_mutation_attempt` (DB trigger blocked UPDATE/DELETE on ledger);
a `ledger` change failed at the DB layer.

## Symptoms
- Trigger exception in logs; an application code path attempted to update/delete a
  posted entry.

## Safe first step
1. Treat it as a fraud or bug signal (risk R-06): capture the query, app context, actor,
  and `request_id` from the log.
2. Do not temporarily disable triggers. If they are disabled, the platform is outside
  its control envelope.

## Investigate
- App bug: find the offending code path (all corrections must go through
  `LedgerReversalService` — ADR-0006) and fix it.
- Malicious/insider: escalate; check audit log for the actor; preserve evidence.

## Fix
- Permanently redirect the code path to `LedgerReversalService`; add a regression test
  asserting posting-only writes.
- Confirm no successful mutation ever occurred (trigger countered it).

## Confirmation
- Trigger counter logs no successful mutation; reversal regression test green.

## Postmortem
- Update the review-evidence; if insider, follow R-06 controls (least privilege + audit).