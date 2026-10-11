# ADR-0001 — Integer minor-unit representation for all money

Status: Accepted (2026-10-10)
Context: Ledger and API must never round-trip float; cross-currency confusion is a top
risk (risk register R-13).
Decision: All money values are stored and transmitted as **integer minor units**
(largest supported currency's subunit, i.e. 1/100; e.g. `500` = MMK 5.00).
Consequences: No `float`/`decimal` in Eloquent casts, migrations, services or API
payloads; ISO-4217 numeric codes for currency. Frontend converts to display
denominations only (frontend track).
Enforced by: `tests/Unit/MigrationConventionsTest.php`; `docs/business/accounting-rules.md`.
References: BR-006, FR-204.