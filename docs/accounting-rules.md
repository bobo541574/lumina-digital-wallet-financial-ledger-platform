# Accounting Rules

Digital Wallet & Financial Ledger Platform

Status: Draft for approval (Phase 00)
Companion to: `docs/business-rules.md`, `docs/transaction-state-machines.md`

This document defines the double-entry model, the chart of accounts, the posting rules
for each money movement, and the reconciliation relationship between the ledger and the
cached wallet balance.

---

## 1. Fundamental principles

1. **Double-entry**: every journal entry has ≥ 2 postings whose debits equal credits in
   the journal's currency. `sum(debit) == sum(credit)`.
2. **Integers only**: all amounts are signed 64-bit integers in currency minor units.
   Postings store positive magnitudes with an explicit `direction` (`debit`/`credit`).
3. **Single currency per journal**: all postings in a journal share the journal currency.
4. **Immutability**: posted journals/postings are append-only; `UPDATE`/`DELETE` are
   denied by application guards and database triggers.
5. **Correction by reversal**: mistakes are fixed by posting an equal-and-opposite
   reversal journal linked via `reverses_journal_entry_id`.
6. **Ledger is the source of truth**: `wallet_balances` is a cached projection of the
   ledger and must reconcile to it at all times.
7. **Atomicity**: postings, balance updates, and outbox insertion happen in one database
   transaction.

---

## 2. Debit/credit sign convention

From the platform's perspective (asset-centric convention):

- **Assets** (cash, bank, provider clearing) increase with **debit**.
- **Liabilities** (customer wallet balances the platform owes) increase with **credit**.
- **Revenue** (fees) increases with **credit**.
- **Equity/expense** follow standard rules.

Customer wallet balances are **liabilities** of the platform. When we credit a customer
wallet account, we owe them more; when we debit it, we owe them less.

Net balance of an account = `sum(debits) − sum(credits)` for assets, and
`sum(credits) − sum(debits)` for liabilities/revenue. For postings we store absolute
`amount` and `direction`; the signed effect is computed by account type.

---

## 3. Chart of accounts

Account `type` is one of `asset`, `liability`, `revenue`, `expense`, `equity`.
Account `code` is a stable human-readable key. `currency_code` binds the account to a
currency (nil for currency-agnostic system accounts is not used; system accounts are
per-currency).

| Code                     | Type      | Scope                 | Purpose                                          |
| ------------------------ | --------- | --------------------- | ------------------------------------------------ |
| `CASH`                   | asset     | per currency          | Platform physical cash / bank operating funds     |
| `PROVIDER_CLEARING`      | asset     | per currency/provider | Funds in transit at a payment provider            |
| `PAYOUT_CLEARING`        | asset     | per currency          | Funds committed to a pending bank/provider payout |
| `CUSTOMER_WALLET`        | liability | per wallet            | One account per customer wallet (platform owes)   |
| `FEE_REVENUE`            | revenue   | per currency          | Platform fees earned                              |
| `SUSPENSE`               | liability | per currency          | Unmatched/deposit awaiting reconciliation        |

Rules:

- Each customer wallet has exactly one `CUSTOMER_WALLET` ledger account, keyed by
  `(wallet_id, currency_code)`.
- System accounts are seeded per currency at setup.
- Accounts have a status (`active`/`closed`); only `active` accounts may receive postings.

---

## 4. Posting rules per operation

Amounts shown as their signed balance effect. `D` = debit, `C` = credit.

### 4.1 Internal transfer — A sends 10,000 MMK to B

| Account                    | Direction | Amount |
| -------------------------- | --------- | ------ |
| `CUSTOMER_WALLET` A        | Debit     | 10,000 |
| `CUSTOMER_WALLET` B        | Credit    | 10,000 |
| Total                      |           | 10,000 = 10,000 |

With fee of 100 MMK (sender pays):

| Account                 | Direction | Amount |
| ----------------------- | --------- | ------ |
| `CUSTOMER_WALLET` A     | Debit     | 10,100 |
| `CUSTOMER_WALLET` B     | Credit    | 10,000 |
| `FEE_REVENUE`           | Credit    | 100    |
| Total                   |           | 10,100 = 10,100 |

### 4.2 Manual deposit — 50,000 MMK received in bank

On `approved -> succeeded` (funds confirmed):

| Account                 | Direction | Amount |
| ----------------------- | --------- | ------ |
| `CASH`                  | Debit     | 50,000 |
| `CUSTOMER_WALLET` C     | Credit    | 50,000 |

(If held in suspense first, debit `SUSPENSE` until confirmed, then reclassify to
`CASH`.)

### 4.3 Provider deposit — 50 USD successful webhook

| Account                 | Direction | Amount |
| ----------------------- | --------- | ------ |
| `PROVIDER_CLEARING`     | Debit     | 5,000  |
| `CUSTOMER_WALLET` C     | Credit    | 5,000  |

(USD `minor_units = 2`, so 50.00 USD = 5,000 minor units.)

### 4.4 Withdrawal reserve — customer requests 20 USD

No ledger entry on reserve. Only a hold is created: `reserved_balance += 2,000`.
Posting is deferred until payout succeeds.

### 4.5 Withdrawal success — payout of 20 USD confirmed

| Account                 | Direction | Amount |
| ----------------------- | --------- | ------ |
| `CUSTOMER_WALLET` C     | Debit     | 2,000  |
| `PAYOUT_CLEARING`       | Credit    | 2,000  |

On bank settlement confirmation, reclassify `PAYOUT_CLEARING` → `CASH`:

| Account                 | Direction | Amount |
| ----------------------- | --------- | ------ |
| `PAYOUT_CLEARING`       | Debit     | 2,000  |
| `CASH`                  | Credit    | 2,000  |

### 4.6 Withdrawal failure — verified no payout

No ledger entry. Release the hold only: `reserved_balance -= 2,000`.

### 4.7 Fee on withdrawal — 1 USD fee

| Account                 | Direction | Amount |
| ----------------------- | --------- | ------ |
| `CUSTOMER_WALLET` C     | Debit     | 100    |
| `FEE_REVENUE`           | Credit    | 100    |

### 4.8 Correction / reversal

To reverse a journal, post a new journal with every direction inverted and equal amounts.
Link via `reverses_journal_entry_id`. The original is never modified.

---

## 5. Journal entry structure

A journal entry records:

- `id`, `currency_code`
- `entry_type` (e.g. `transfer`, `deposit`, `withdrawal`, `fee`, `reversal`)
- `source_type`, `source_id` — the business operation that caused it
- `description`
- `occurred_at`, `posted_at`
- `reverses_journal_entry_id` (nullable)
- `status` (`posted`, `reversed`)

A posting records:

- `id`, `journal_entry_id`, `ledger_account_id`
- `currency_code` (must equal journal currency)
- `direction` (`debit`/`credit`)
- `amount` (positive integer, minor units)
- `wallet_id` (nullable; set for customer wallet accounts)
- `created_at`

---

## 6. Posting validation (LedgerPostingService contract)

`post(JournalEntryData): PostedJournalResult` must enforce, in order:

1. Journal has ≥ 2 postings.
2. All posting accounts exist and are `active`.
3. All posting currencies equal the journal currency.
4. Every amount is a positive integer (> 0).
5. `sum(debit) == sum(credit)` (balanced).
6. No existing journal for the same `(source_type, source_id)` (duplicate source guard),
   unless explicitly permitted for corrections.
7. On success: persist journal + postings in one transaction, update any affected cached
   balances, and write an outbox message.

Rejection raises a domain exception mapping to a stable API error code (see
`docs/api-error-codes.md`).

Note: a single database `CHECK` constraint cannot sum across multiple posting rows. The
balance invariant is enforced by the transactional service, with database triggers and
restricted DB permissions as defense in depth.

---

## 7. Wallet balance projection

`wallet_balances` caches, per wallet:

- `posted_balance` = ledger-derived balance of the wallet's `CUSTOMER_WALLET` account.
- `reserved_balance` = sum of active/in-flight holds.
- `available_balance` = `posted_balance − reserved_balance` (computed, not stored, or
  stored and kept consistent — decision recorded in implementation).

Reconciliation (Phase 08) recomputes `posted_balance` from `ledger_postings` and compares
to the cached value, and recomputes reserved from `wallet_holds`. Any mismatch raises a
discrepancy and is resolved via the approved correction workflow — never by silent
overwriting.

Update protocol: the cached balances are only mutated inside the same transaction that
posts the corresponding journal, while holding the wallet row lock.

---

## 8. Rounding and fees

- No rounding occurs in MVP because all supported movements are single-currency and
  amounts are integers in minor units.
- Fee amounts are integers in the same currency and posted to `FEE_REVENUE`.
- Any future rounding (from FX or percentage fees) must stay within one currency and be
  recorded explicitly; never silently net amounts.

---

## 9. Period and reporting rules

- Statements report opening balance, movements (with running balance), and closing
  balance for a wallet and time range, in minor units with currency metadata.
- Totals on a statement must equal the sum of the corresponding postings — verified by
  tests.
- Backdated `occurred_at` is allowed for provider events but `posted_at` follows the
  actual commit time; both are retained.

---

## 10. Worked example — full lifecycle

1. Customer C deposits 100 USD via provider → on success:
   `D PROVIDER_CLEARING 10,000 / C CUSTOMER_WALLET C 10,000`; posted_balance = 10,000.
2. C requests withdrawal of 20 USD → hold created; reserved = 2,000; available = 8,000.
   No ledger entry.
3. Provider times out → `reconciling`; hold retained.
4. Provider later confirms success → `D CUSTOMER_WALLET C 2,000 / C PAYOUT_CLEARING 2,000`;
   hold consumed; posted = 8,000; reserved = 0; available = 8,000.
5. Settlement arrives → `D PAYOUT_CLEARING 2,000 / C CASH 2,000`.

At every step, `available_balance = posted_balance − reserved_balance` and the ledger
balances.
