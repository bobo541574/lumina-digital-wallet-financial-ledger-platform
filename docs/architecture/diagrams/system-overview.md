# System Overview Diagram

This document provides a high-level view of the Lumina platform's components and data flows.

## Component architecture

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                              Client (SPA)                                    │
│                    Vue 3 + TypeScript + Pinia + Vite + Tailwind CSS           │
└───────────────────────────────────────────────┬─────────────────────────────┘
                                                │ HTTPS / REST + WebSocket
                                                ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                            Laravel 13 (API)                                  │
│  ┌─────────────┬─────────────┬─────────────┬─────────────┬─────────────────┐ │
│  │  Identity    │  Wallets     │  Ledger      │  Transfers   │  Deposits       │ │
│  │  (Auth/RBAC) │  (Balances)  │  (DE Ledger) │  (Internal)  │  (Manual+Prov.) │ │
│  ├─────────────┼─────────────┼─────────────┼─────────────┼─────────────────┤ │
│  │  Withdrawals │  PaymentProv │  Reconciliation│  Audit       │  Admin/Notif.   │ │
│  │  (Holds)     │  (Adapters)  │  (Layers)      │  (Trail)     │  (Outbox)       │ │
│  └─────────────┴─────────────┴─────────────┴─────────────┴─────────────────┘ │
└───────────────────────────────────────────────┬─────────────────────────────┘
                                                │
                                    ┌───────────┼───────────┐
                                    ▼           ▼           ▼
                              ┌─────────────┐ ┌─────────┐ ┌─────────────┐
                              │  PostgreSQL  │ │  Redis   │ │  Laravel     │
                              │  (Source of  │ │  (Cache/ │ │  Reverb      │
                              │  truth)      │ │  Queues) │ │  (WebSocket) │
                              └─────────────┘ └─────────┘ └─────────────┘
```

## Key flows

### Transfer flow (high-level)

1. Client submits transfer with `Idempotency-Key` + request hash.
2. API validates ownership, currency, funds (available = posted - reserved).
3. Service locks wallets/balances in deterministic order.
4. Posts balanced double-entry journal + updates cached balances (same transaction).
5. Records outbox event `transfer.completed`.
6. Outbox processor delivers event; notifications created for sender/recipient.
7. Response returned to client.

### Deposit flow

- **Manual**: create (awaiting_approval) → approve (segregation of duties) → confirm (posts to ledger, credits balance, emits event).
- **Provider**: create intent → provider processes → webhook verifies signature/dedupe/match → settles (posts ledger, credits balance) or quarantines on mismatch.

### Withdrawal flow

- Request reserves full amount+fee via hold (reserved_balance increases, available drops).
- Initiate calls provider; timeout → reconcile (hold retained). Success → post double-entry, consume hold, emit success. Failure → release hold, emit failure.

### Reconciliation

- Layer 1 (Wallet): posted from ledger vs cached; reserved from active holds vs cached.
- Layer 2/3 (Provider/Settlement): platform vs provider records. All discrepancies recorded; never auto-corrected.
