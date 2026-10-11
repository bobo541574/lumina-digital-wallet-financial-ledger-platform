# API Error Codes

Digital Wallet & Financial Ledger Platform

Status: Draft for approval (Phase 00)
Companion to: `docs/business/business-rules.md`

All API errors use a single machine-readable envelope. Clients branch on `error.code`,
never on message text.

---

## 1. Response envelope

Success:

```json
{
  "data": { },
  "meta": { "request_id": "01J..." }
}
```

Error:

```json
{
  "error": {
    "code": "INSUFFICIENT_FUNDS",
    "message": "Available balance is too low for this transfer.",
    "details": { "available": 8000, "required": 10100, "currency": "MMK" },
    "request_id": "01J..."
  }
}
```

Rules:

- `request_id` is the correlation id, also returned in the `X-Request-Id` response header.
- `code` is stable and documented; `message` is human-friendly and may change.
- `details` is optional and machine-usable (never contains secrets or full PANs).

---

## 2. HTTP status mapping

| Status | Meaning                                                        |
| ------ | -------------------------------------------------------------- |
| `400`  | Malformed request / validation-independent bad input           |
| `401`  | Unauthenticated                                                |
| `403`  | Authenticated but not authorized (ownership/role)              |
| `404`  | Resource not found (also used to hide others' resources)       |
| `409`  | Conflict (state, idempotency, concurrency)                     |
| `422`  | Validation failed / business rule violation                    |
| `423`  | Locked (wallet frozen)                                         |
| `429`  | Rate limited                                                   |
| `500`  | Unexpected server error                                        |
| `503`  | Dependency unavailable (provider/db/redis)                     |

---

## 3. Error code catalog

### 3.1 Authentication & authorization

| Code                     | HTTP | Notes                                          |
| ------------------------ | ---- | ---------------------------------------------- |
| `UNAUTHENTICATED`        | 401  | Missing/invalid token                          |
| `TOKEN_EXPIRED`          | 401  | Expired access token                           |
| `FORBIDDEN`              | 403  | Role/permission denied                         |
| `NOT_OWNER`              | 403  | Resource belongs to another actor              |
| `RESOURCE_NOT_FOUND`     | 404  | Not found or hidden for authorization          |
| `MFA_REQUIRED`           | 403  | Admin/finance action needs MFA                 |

### 3.2 Validation

| Code                     | HTTP | Notes                                          |
| ------------------------ | ---- | ---------------------------------------------- |
| `VALIDATION_FAILED`      | 422  | Field errors in `details.fields`               |
| `UNSUPPORTED_CURRENCY`   | 422  | Currency not enabled                           |
| `CURRENCY_MISMATCH`      | 422  | Operation mixes currencies or wallet mismatch  |
| `INVALID_AMOUNT`         | 422  | Zero/negative/non-integer amount               |
| `AMOUNT_TOO_LARGE`       | 422  | Exceeds per-transaction limit                  |

### 3.3 Wallet & balance

| Code                     | HTTP | Notes                                          |
| ------------------------ | ---- | ---------------------------------------------- |
| `WALLET_NOT_FOUND`       | 404  |                                                |
| `WALLET_FROZEN`          | 423  | Debit attempted on frozen wallet               |
| `WALLET_CLOSED`          | 409  | Operation on closed wallet                     |
| `INSUFFICIENT_FUNDS`     | 422  | `available_balance < amount + fees`            |
| `SELF_TRANSFER`          | 422  | Sender and recipient identical                 |

### 3.4 Idempotency

| Code                          | HTTP | Notes                                     |
| ----------------------------- | ---- | ----------------------------------------- |
| `IDEMPOTENCY_KEY_REQUIRED`    | 400  | Mutation without `Idempotency-Key`        |
| `IDEMPOTENCY_KEY_REUSED`      | 409  | Same key, different payload hash          |
| `IDEMPOTENCY_IN_PROGRESS`     | 409  | Same key, original still processing       |
| `IDEMPOTENCY_KEY_INVALID`     | 400  | Key format/length invalid                 |

### 3.5 Ledger

| Code                     | HTTP | Notes                                          |
| ------------------------ | ---- | ---------------------------------------------- |
| `LEDGER_UNBALANCED`      | 422  | total debit ≠ total credit                     |
| `LEDGER_ACCOUNT_INACTIVE`| 422  | Posting to non-active account                  |
| `LEDGER_ACCOUNT_INVALID` | 422  | Unknown/mismatched account                     |
| `LEDGER_DUPLICATE_SOURCE`| 409  | Journal already exists for source              |
| `LEDGER_IMMUTABLE`       | 409  | Attempt to mutate a posted entry               |
| `REVERSAL_ALREADY_EXISTS`| 409  | Journal already reversed                       |
| `JOURNAL_NOT_REVERSIBLE` | 409  | Journal cannot be reversed (e.g. a reversal)   |

### 3.6 Deposits

| Code                     | HTTP | Notes                                          |
| ------------------------ | ---- | ---------------------------------------------- |
| `DEPOSIT_NOT_FOUND`      | 404  |                                                |
| `DEPOSIT_INVALID_STATE`  | 409  | Transition not allowed from current state      |
| `DEPOSIT_SELF_APPROVAL`  | 403  | Approver is the initiator (segregation of duty)|
| `DEPOSIT_ALREADY_SETTLED`| 409  | Already `succeeded`                            |

### 3.7 Withdrawals

| Code                        | HTTP | Notes                                       |
| --------------------------- | ---- | ------------------------------------------- |
| `WITHDRAWAL_NOT_FOUND`      | 404  |                                             |
| `WITHDRAWAL_INVALID_STATE`  | 409  | Transition not allowed                      |
| `HOLD_RELEASE_UNSAFE`       | 409  | Cannot release hold; payout unconfirmed     |
| `PAYOUT_ALREADY_SETTLED`    | 409  | Already succeeded/failed                    |
| `HOLD_EXPIRED`              | 409  | Hold expired before action                  |

### 3.8 Providers & webhooks

| Code                        | HTTP | Notes                                       |
| --------------------------- | ---- | ------------------------------------------- |
| `WEBHOOK_SIGNATURE_INVALID` | 400  | Signature verification failed               |
| `WEBHOOK_DUPLICATE`         | 200* | Already processed; safe ack (not an error)  |
| `WEBHOOK_QUARANTINED`       | 202  | Amount/currency/ref mismatch; held for recon|
| `PROVIDER_UNAVAILABLE`      | 503  | Provider unreachable                        |
| `PROVIDER_TIMEOUT`          | 202  | Outcome unknown → `reconciling`             |
| `PROVIDER_REJECTED`         | 422  | Provider explicitly declined                |

\* `WEBHOOK_DUPLICATE` returns `2xx` so the provider stops retrying; it is recorded but
is not treated by the client as an error.

### 3.9 Reconciliation

| Code                                    | HTTP | Notes                                    |
| --------------------------------------- | ---- | ---------------------------------------- |
| `RECONCILIATION_ITEM_NOT_FOUND`         | 404  | Unknown discrepancy item                 |
| `RECONCILIATION_ITEM_ALREADY_RESOLVED`  | 409  | Item already resolved/ignored            |

### 3.10 Concurrency & infrastructure

| Code                     | HTTP | Notes                                          |
| ------------------------ | ---- | ---------------------------------------------- |
| `CONCURRENT_MODIFICATION`| 409  | Optimistic/lock conflict; safe to retry        |
| `TEMPORARY_FAILURE`      | 503  | Transient; retry with backoff                  |
| `RATE_LIMITED`           | 429  | `Retry-After` header set                       |
| `INTERNAL_ERROR`         | 500  | Unexpected                                     |

---

## 4. Retryability

| Code                       | Safe to retry? | Guidance                              |
| -------------------------- | -------------- | ------------------------------------- |
| `CONCURRENT_MODIFICATION`  | Yes            | Retry same idempotency key, backoff   |
| `TEMPORARY_FAILURE`        | Yes            | Retry with backoff                    |
| `PROVIDER_TIMEOUT`         | No             | Do not assume failure; watch status   |
| `IDEMPOTENCY_IN_PROGRESS`  | Yes (later)    | Poll or retry after short delay       |
| `RATE_LIMITED`             | Yes (later)    | Honor `Retry-After`                   |
| `INSUFFICIENT_FUNDS`       | No             | Terminal for that request             |
| `IDEMPOTENCY_KEY_REUSED`   | No             | Use a new key                         |

---

## 5. Cross-cutting rules

- Every mutating endpoint requires an `Idempotency-Key` header; missing → 
  `IDEMPOTENCY_KEY_REQUIRED`.
- `403` vs `404`: to prevent enumeration, resources owned by others return `404`
  (`RESOURCE_NOT_FOUND`) rather than `403`, except where the actor legitimately knows the
  resource exists (same-org admin flows).
- Errors never leak stack traces, SQL, provider secrets, or internal ids beyond what the
  actor is authorized to see.
- All error responses include `X-Request-Id` for correlation with logs and `audit_events`.
