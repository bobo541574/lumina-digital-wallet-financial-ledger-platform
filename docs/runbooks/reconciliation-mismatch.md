# Runbook: Wallet ↔ Ledger Reconciliation Mismatch

Trigger: alert `reconciliation_variance`; or support reports a balance discrepancy.

## Symptoms
- `wallet_balances.available`/`reserved` differs from summed `ledger` entries for a
  currency, or the daily reconciliation job reports variance.

## Safe first step
1. **Freeze money movements for the affected wallets** (status freeze) so no new
  journal rows land while investigating. Do **not** adjust balances.
2. Capture the current rows: wallet balance and the ledger aggregate for the same
  currency:
  `php artisan tinker --execute '…wallet ledger sums…'` (read-only).

## Investigate
- Look for a corrupted deposit/withdrawal/transfer window (search audit + partition for
  that window).
- Check for reverses of reverses (should be blocked — ADR-0006) and for any in-flight
  concurrency that recorded the journal but failed to update balance.

## Fix
- Create a single missing posting pair via `LedgerReversalService` **or** if the source
  transaction is missing entirely, post a corrected balanced pair **with a reason** and
  reference. Fix the balance only through a completed journal pair — never by direct
  `UPDATE`.
- Resume movements and run a verification reconciliation.

## Confirmation
- `sum(posted) == 0` per currency over the window; wallet == ledger aggregate;
  reconciliation job reports zero variance.

## Postmortem
- Record the cause in the risk register; if the window had concurrency, extend the
  concurrency test set (ADR-0003).