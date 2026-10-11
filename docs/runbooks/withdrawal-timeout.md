# Runbook: Withdrawal Timeout (Reconciling)

Trigger: alert `withdrawal_timeout`; withdrawals linger in `reconciling` beyond the SLA;
risk R-05 (provider timeout misclassified).

## Symptoms
- Withdrawals in `reconciling` state; holds still reserved; customers report late payouts.

## Safe first step
1. Do **not** release holds or mark failed from the UI. Timeout → `reconciling`
  intentionally retains the hold (FR-506).
2. Pull the provider-side state if the adapter exposes it (payout query).

## Investigate
- Provider processing outage → keep `reconciling`, retry payout status query.
- Provider confirms payout settled → advance to `succeeded` and consume the hold with a
  single ledger posting.
- Provider confirms rejected → verify no payout happened; then release the hold as a
  revenue-neutral correction and return funds.

## Fix
- Each path above is a single, idempotent state transition with its own event;
  idempotency key scoped per payout to guarantee no double payout (FR-508).

## Confirmation
- No `reserved_balance` leak; each inverted withdrawal ends `succeeded` or `failed` with
  a matching ledger net effect; zero variance.

## Postmortem
- Review provider SLA config; if outage repeatable, raise provider status channel.