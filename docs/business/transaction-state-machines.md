# Transaction State Machines

Digital Wallet & Financial Ledger Platform

Status: Draft for approval (Phase 00)
Companion to: `docs/business/business-rules.md`, `docs/business/accounting-rules.md`

Every state machine lists: states, allowed transitions, the trigger/actor, the guard
that must hold, and the side effects (ledger/balance/hold). Transitions not listed are
forbidden. Every transition writes an `audit_events` record.

Legend: `(actor) --[trigger / guard]--> next state :: side effects`

---

## 1. Wallet lifecycle

States: `active`, `frozen`, `closed`.

```
active  --(admin: freeze / audited)--> frozen
frozen  --(admin: unfreeze / audited)--> active
active  --(admin: close / available_balance == 0 and no active holds)--> closed
frozen  --(admin: close / available_balance == 0 and no active holds)--> closed
```

- `closed` is terminal.
- Freezing does not affect inbound credits or reversals.
- Closing requires zero available balance and zero active holds.

---

## 2. Internal transfer

States: `pending`, `succeeded`, `failed`.
(Internal transfers are synchronous and atomic; there is no external unknown outcome.)

```
pending --(system: validate+lock+post / sender available_balance >= amount+fee,
          wallets active, same currency)--> succeeded :: post balanced journal,
          debit sender / credit recipient, update both balances, write outbox
pending --(system: guard fails)--> failed :: no postings, error returned
```

Invariants:

- Sender and recipient wallets must be `active` and the same currency.
- Wallet rows are locked with `SELECT ... FOR UPDATE` in deterministic ascending id
  order to avoid deadlocks.
- Balance cache and postings are committed in one transaction.
- Notifications are emitted after commit via the transactional outbox, never inside the
  financial transaction.
- Replaying the same idempotency key returns the original `succeeded` result.

Failure handling:

- Insufficient funds → `failed` (`INSUFFICIENT_FUNDS`), no postings.
- Transient serialization/deadlock error → retried a bounded number of times; if still
  failing → `failed` with `TEMPORARY_FAILURE`.

---

## 3. Deposit (manual)

States: `pending`, `awaiting_approval`, `approved`, `succeeded`, `failed`, `cancelled`.

```
pending            --(customer: submit evidence/amount)--> awaiting_approval
awaiting_approval  --(finance_operator: approve / amount verified, not self-approved)--> approved
awaiting_approval  --(finance_operator: reject / reason)--> failed
approved           --(finance_operator: confirm funds received / bank/proof checked)--> succeeded
                    :: post balanced journal, credit customer wallet, update balance, outbox
approved           --(finance_operator: cannot confirm)--> failed
pending|awaiting_approval --(customer: cancel)--> cancelled
```

Key rule: `approved != succeeded`. Funds are only credited on the `approved -> succeeded`
transition after receipt is confirmed.

---

## 4. Deposit (provider)

States: `pending`, `awaiting_payment`, `processing`, `succeeded`, `failed`, `reconciling`,
`cancelled`.

```
pending          --(customer: create intent)--> awaiting_payment
awaiting_payment --(system: checkout initiated)--> processing
processing       --(system: verified webhook / amount+currency+ref match)--> succeeded
                    :: post balanced journal, credit wallet, update balance, outbox
processing       --(system: verified webhook / payment failed)--> failed
processing       --(system: timeout, outcome unknown)--> reconciling
reconciling      --(scheduler: status query / provider says success)--> succeeded :: post as above
reconciling      --(scheduler: status query / provider says failure)--> failed
awaiting_payment --(customer: cancel)--> cancelled
```

Webhook pipeline (in order):

1. Verify signature. Invalid → reject + record (`webhook_deliveries`), no state change.
2. Insert provider event into `webhook_inbox` keyed by `(provider, event_id)`.
   Duplicate → acknowledge `2xx`, stop (no re-posting).
3. Load intent; verify amount, currency, and reference match expected order.
   Mismatch → quarantine for reconciliation, no posting.
4. Atomically post journal + update wallet + mark intent `succeeded` + write outbox.
5. Acknowledge `2xx`.

---

## 5. Withdrawal

States: `pending`, `reserved`, `processing`, `succeeded`, `failed`, `cancelled`,
`reconciling`.

```
pending    --(customer: request / wallet active, available_balance >= amount+fee)--> reserved
             :: create hold (reserved_balance += amount+fee); no ledger movement yet
reserved   --(system/admin: approve + initiate payout)--> processing
             :: call provider, store provider_ref
processing --(provider: confirmed payout)--> succeeded
             :: post journal (reduce wallet liability, debit payout clearing);
                consume hold (reserved_balance -= hold)
processing --(provider: confirmed failure)--> failed
             :: release hold (reserved_balance -= hold); no ledger movement
processing --(timeout, outcome unknown)--> reconciling :: keep hold, do NOT release
reconciling--(provider: status = success)--> succeeded :: post + consume hold
reconciling--(provider: status = failure)--> failed    :: release hold (verified no payout)
reserved   --(admin/customer: cancel before payout)--> cancelled :: release hold
reserved   --(system: hold expired AND verified no payout initiated)--> failed :: release hold
pending    --(customer: cancel)--> cancelled
```

Hard rules:

- A hold reduces `available_balance`; it is **not** a ledger debit.
- The hold is only released once it is confirmed no payout occurred.
- While `reconciling`, the hold stays; the operation is never marked `failed` on timeout.
- Double payout is prevented by idempotency + provider reference uniqueness.

---

## 6. Hold lifecycle

States: `active`, `consumed`, `released`, `expired`.

```
active   --(withdrawal succeeds)--> consumed :: reserved_balance -= amount
active   --(withdrawal fails/cancelled, verified no payout)--> released :: reserved_balance -= amount
active   --(expiry passes, verified no payout)--> expired :: reserved_balance -= amount
```

Invariant: `wallet_balances.reserved_balance` always equals the sum of `active` holds +
in-flight holds for that wallet, checked inside the same transaction.

---

## 7. Journal / posting lifecycle

Entries are immutable once posted.

```
draft   --(system: validate balanced/currency/active accounts)--> posted   (append-only)
posted  --(system: correction)--> reversed :: new reversing journal created,
                                               original unchanged
```

- No transition out of `posted` mutates the row. Reversal creates new rows.
- A `posted` journal may be referenced by at most one reversing journal.

---

## 8. Reconciliation run

States: `running`, `completed`, `completed_with_discrepancies`, `failed`.

```
running  --(system: all layers compared)--> completed | completed_with_discrepancies
running  --(system: fatal error)--> failed
```

Discrepancies are classified as: `missing`, `duplicate`, `amount_mismatch`,
`status_mismatch`, `timing`. Each produces `reconciliation_items` for review. No
automatic balance correction — resolution goes through the approved correction workflow.

---

## 9. Idempotency record

States: `in_progress`, `completed`, `failed`.

```
(none)      --(first request)--> in_progress
in_progress --(operation succeeds)--> completed :: store response
in_progress --(operation fails)--> failed       :: store error response
completed   --(same key+same payload)--> completed :: return stored response (no-op)
completed   --(same key+different payload)--> conflict (422/409), state unchanged
```

---

## 10. Transition → side effect matrix

| Machine     | Transition              | Ledger | Balance | Hold | Outbox |
| ----------- | ----------------------- | ------ | ------- | ---- | ------ |
| Transfer    | pending→succeeded       | post   | both    | —    | yes    |
| Deposit     | processing→succeeded    | post   | credit  | —    | yes    |
| Withdrawal  | pending→reserved        | —      | reserved| +hold| —      |
| Withdrawal  | processing→succeeded    | post   | reserved| −hold| yes    |
| Withdrawal  | processing→failed       | —      | reserved| −hold| yes    |
| Withdrawal  | processing→reconciling  | —      | —       | keep | yes    |
| Correction  | posted→reversed         | post   | adjust  | —    | yes    |
