# Evidence — Financial Database Constraints

| Field | Value |
| ----- | ----- |
| Date | 2026-10-10 |
| Revision | `eb144b5` + uncommitted working tree |
| Command | `vendor/bin/pest tests/Feature/Database/DatabaseConstraintTest.php --compact` |
| Result | **7 passed**, 27 assertions |
| Environment | PHP 8.4.13; PostgreSQL 16.10; Laravel 13.35.0; DB `lumina_test` |

These tests verify the database — not just the service layer — enforces the financial
invariants the ledger and reconciliation trust (defence in depth).

## Verified invariants

| Invariant | Mechanism | Proof |
| --------- | --------- | ----- |
| Balance can never go negative | `wallet_balances_posted_non_negative`, `..._reserved_non_negative` CHECKs | update to `posted_balance = -1` rejected |
| Ledger postings are positive and single-direction | `ledger_postings_amount_positive`, `ledger_postings_direction_check` | constraint presence + definition |
| Posted ledger entries are immutable | `ledger_postings_immutable`, `journal_entries_immutable` triggers | UPDATE and DELETE both rejected |
| No self-transfer | `transfers_distinct_wallets` CHECK | raw insert with equal wallets rejected |
| Amounts positive on transfers/withdrawals/holds | `*_amount_positive` CHECKs | constraint presence + definition |
| Wallet currency cannot drift from its ledger account | composite FK `(wallet_id, currency_code)` | changing `wallets.currency_code` on a funded wallet rejected |
| Idempotency keys unique per scope+actor | `idempotency_keys_scope_actor_id_key_unique` | constraint presence |
| One wallet per (user, currency) | `wallets_user_id_currency_code_unique` | constraint presence |
| Ledger account type is one of the known set | `ledger_accounts_type_check` | invalid `type` rejected |

## Notes

- The test database (`lumina_test`) is migrated fresh, so every constraint above is
  present and enforced by the test run.
- A global unique constraint on `payment_events.id` cannot be expressed on a
  partitioned table (Postgres requirement); uniqueness is by application convention
  (UUID) instead. Documented in the table migration.