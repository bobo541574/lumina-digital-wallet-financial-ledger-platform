# Digital Wallet & Financial Ledger Platform — Implementation Blueprint

ဒီ project ကို Production-oriented Digital Wallet Platform အဖြစ် တည်ဆောက်မယ်။ Phase တွေကို အစဉ်လိုက်ခွဲပြီး တစ်ဆင့်ချင်း implement လုပ်နိုင်အောင် Business Rules → Database → Backend Architecture → Implementation → Testing → Deployment အထိ သတ်မှတ်ထားမယ်။

အဓိက ရည်ရွယ်ချက်က Wallet CRUD တွေ ရေးရုံမဟုတ်ဘဲ ငွေကြေးစနစ်တစ်ခုမှာ အရေးကြီးတဲ့ Double-Entry Ledger, Transaction Atomicity, Idempotency, Concurrency Control, Reconciliation နဲ့ PostgreSQL Partitioning တွေကို လက်တွေ့အသုံးချတတ်ဖို့ပါ။

## 1. Project scope နဲ့ architecture decisions

Project: Digital Wallet & Financial Ledger

Laravel · PostgreSQL · Redis · Vue 3 · TypeScript

Architecture: Modular Monolith

အစပိုင်း Microservices မခွဲသေးဘဲ domain boundaries တွေကို ရှင်းရှင်းလင်းလင်း သတ်မှတ်မယ်။

Accounting: Double-Entry Ledger + Cached Balance

Ledger က financial record of truth ဖြစ်ပြီး wallet balance က မြန်မြန်ဖတ်နိုင်ဖို့ materialized operational balance ဖြစ်မယ်။ နှစ်ခုကို reconciliation လုပ်မယ်။

Partitioning: Hybrid Strategy

High-volume events တွေကို partition လုပ်မယ်။ Ledger ကို benchmark အရ ဆုံးဖြတ်မယ်။

Business: Single platform, multi-currency

Manual နဲ့ payment-provider deposit/withdrawal နှစ်မျိုးလုံး ပါဝင်မယ်။

### Frontend & UI (deferred)

UI ကို သီးခြား phase အနေနဲ့ ထားမယ်။ UI phase စတင်တဲ့အခါ component decisions အတွက် reference က `https://ui.shadcn.com/docs/components` ပေးထားတဲ့ flows ဖြစ်ပြီး အဲဒီ patterns အတိုင်း implement လုပ်မယ်။

* Stack — Vue 3 + TypeScript (project scope အတိုင်း)
* Reference — `ui.shadcn.com/docs/components` component flows (shadcn/ui က React-native ဖြစ်တာမို့ Vue port ဖြစ်တဲ့ shadcn-vue နဲ့ တူညီတဲ့ component set/flows ကို သုံးမယ်)
* Approach — Components တွေကို UI phase မှာ flow-by-flow လိုက်ဖို့ပြီး project conventions (PHP API, RBAC permissions, audit) နဲ့ တွဲဖော်ပြမယ်
* Note — ဒီ decision က နောက်ပိုင်း UI milestone တိုင်းမှာ developer/agent တွေ ဦးစွာ reference လုပ်ရမယ့် စာချုပ်ဖြစ်တယ်

### MVP ထဲမှာ ပါဝင်မယ့် features

* User authentication, roles, permissions

* Multi-currency wallets

* Internal wallet-to-wallet transfers

* Double-entry ledger နဲ့ transaction history

* Manual / provider deposits

* Manual / provider withdrawals

* Withdrawal holds / reserved balance

* Idempotency နဲ့ duplicate request protection

* Provider webhook verification

* Admin approval နဲ့ audit trail

* Transactional outbox, queues, notifications

* Reconciliation reports

* Partitioned payment/audit events

* Automated tests, monitoring, backup/restore

အစပိုင်းမှာ FX exchange, lending, crypto, card acquiring နဲ့ cross-border settlement တွေကို MVP scope ထဲ မထည့်သေးဘူး။ Multi-currency wallet ရှိတာနဲ့ currency exchange ကို support လုပ်ပြီးသားလို့ မယူဆရပါဘူး။


## 2. Phase roadmap

### 00

Requirements & Financial Rules

Business rules, state machines, accounting policy

### 01

Project Foundation

Laravel setup, modules, authentication, CI

### 02

Database & Ledger Core

Currencies, wallets, accounts, journals, postings

### 03

Wallet Balance & Internal Transfer

Atomic transfer, locking, idempotency

### 04

Deposits

Manual approval, provider payment, webhook inbox

### 05

Withdrawals

Holds, payout state machine, unknown outcomes

### 06

Transaction History & Admin

Statements, permissions, audit trails

### 07

Events, Outbox & Partitioning

Async processing, partition automation

### 08

Reconciliation & Resilience

Ledger/balance/provider matching, recovery

### 09

Security & Performance

Threat checks, concurrency tests, benchmarks

### 10

Deployment & Operations

Production rollout, monitoring, backup/restore

## Phase 00 — Requirements & Financial Rules

ရည်ရွယ်ချက်: Code မရေးခင် ငွေဘယ်လိုဝင်၊ ဘယ်လိုထွက်၊ ဘယ်အချိန်မှာ transaction အောင်မြင်တယ်လို့ သတ်မှတ်မလဲ ဆိုတာကို တိတိကျကျ သတ်မှတ်မယ်။

### Implementation tasks

1. User roles သတ်မှတ်ရန် — `customer`, `admin`, `finance_operator`, `auditor`

2. Wallet status — `active`, `frozen`, `closed`

3. Deposit, withdrawal, transfer state machine တွေ သတ်မှတ်ရန်

4. Currency minor units သတ်မှတ်ရန် — ဥပမာ USD 2 decimal၊ MMK 0 decimal

5. Ledger account chart သတ်မှတ်ရန်

6. Provider failure, timeout, duplicate webhook, refund/reversal rules သတ်မှတ်ရန်

7. API error codes, idempotency scope, authorization rules သတ်မှတ်ရန်

### Core financial invariants

* Currency တစ်ခုချင်းစီအလိုက် journal entry တစ်ခုစီမှာ total debit = total credit ဖြစ်ရမယ်။

* Posted ledger entries တွေကို တိုက်ရိုက် update/delete မလုပ်ရ။

* မှားယွင်းတဲ့ posting ကို reversal entry အသစ်နဲ့ ပြင်ရမယ်။

* Same idempotency key + same request ကို ထပ်ပို့ရင် duplicate money movement မဖြစ်ရ။

* Same key + different request payload ဖြစ်ရင် conflict ပြန်ပေးရမယ်။

* Wallet balance နဲ့ reserved balance ကို ledger/active holds နဲ့ reconciliation လုပ်နိုင်ရမယ်။

* Provider timeout ဖြစ်တာနဲ့ payment failed လို့ မသတ်မှတ်ရ။ အခြေအနေမသေချာသေးရင် `reconciling` အဖြစ်ထားရမယ်။

Deliverables: `docs/business-rules.md`, `docs/transaction-state-machines.md`, `docs/accounting-rules.md`, `docs/api-error-codes.md`

Done when: Transfer, deposit, withdrawal ဖြစ်စဉ်တိုင်းအတွက် state transition နဲ့ accounting entries ကို example နဲ့ ရှင်းပြနိုင်ရမယ်။

## Phase 01 — Project Foundation

ရည်ရွယ်ချက်: Feature တွေကို သီးခြားစီ တိုးချဲ့၊ စမ်းသပ်၊ maintain လုပ်နိုင်တဲ့ foundation တည်ဆောက်ရန်။

### Backend

* Laravel application, PostgreSQL connection, Redis configuration

* Authentication, role/permission management

* Request validation, DTOs, API Resources

* Service container interface bindings

* Logging, exception handling, request correlation IDs

* Environment configuration, secrets management

* Pest testing, CI pipeline, database migrations

### Suggested module structure

```
app/
├── Modules/
│   ├── Identity/
│   ├── Wallets/
│   ├── Ledger/
│   ├── Transfers/
│   ├── Deposits/
│   ├── Withdrawals/
│   ├── PaymentProviders/
│   ├── Reconciliation/
│   ├── Audit/
│   └── Shared/
└── Providers/

routes/
├── api.php
└── modules/
    ├── identity.php
    ├── wallets.php
    ├── transfers.php
    ├── deposits.php
    └── withdrawals.php
```

Module တစ်ခုစီမှာ အောက်ပါ structure ကို လိုအပ်သလို သုံးမယ်။

```
Transfers/
├── Controllers/
├── Requests/
├── DTOs/
├── Services/
├── Repositories/
│   ├── Contracts/
│   └── Eloquent/
├── Models/
├── Enums/
├── Policies/
└── Tests/
```

Controller → FormRequest → DTO → Service → Repository → Model ဆိုတဲ့ flow ကို သုံးနိုင်ပေမယ့် financial rules တွေကို Controller/Repository ထဲမှာ မပျံ့နှံ့စေရပါဘူး။ Domain rules တွေကို Service/Domain layer မှာ ထားရမယ်။

Deliverables: Running application, auth, module skeleton, CI, test conventions, error response format.

Done when: Clean environment တစ်ခုမှာ migration, seed, test, application boot တို့ကို အောင်မြင်စွာ လုပ်နိုင်ရမယ်။

## Phase 02 — Database & Ledger Core

ဒီ Phase က project ရဲ့ အရေးအကြီးဆုံး foundation ဖြစ်တယ်။

### Database implementation order

| Order | Tables                       | Purpose                             |
| ----- | ---------------------------- | ----------------------------------- |
| 1     | `users`, roles/permissions   | Identity and authorization          |
| 2     | `currencies`                 | Currency metadata                   |
| 3     | `wallets`, `wallet_balances` | Wallet and cached balance           |
| 4     | `ledger_accounts`            | Chart of accounts                   |
| 5     | `journal_entries`            | Financial transaction headers       |
| 6     | `ledger_postings`            | Debit/credit lines                  |
| 7     | `idempotency_keys`           | Duplicate request prevention        |
| 8     | `audit_events`               | Security and administrative history |

### Ledger service contract

`LedgerPostingService` ကို အခြား financial modules တွေ အသုံးပြုမယ့် central boundary အဖြစ် သတ်မှတ်မယ်။

PHP

```
interface LedgerPostingServiceInterface
{
    public function post(
        JournalEntryData $entry
    ): PostedJournalResult;
}
```

ဒီ signature က design example ဖြစ်ပြီး DTO/result class တွေကို project ထဲမှာ သီးခြား define လုပ်ရမယ်။

Service က အောက်ပါအချက်တွေကို စစ်ဆေးရမယ်။

1. Journal entry ရဲ့ account တွေဟာ active ဖြစ်ရမယ်။

2. Journal currency နဲ့ posting account currencies ကိုက်ညီရမယ်။

3. Debit/credit amount တွေ positive ဖြစ်ရမယ်။

4. Total debit = total credit ဖြစ်ရမယ်။

5. Source transaction အတွက် duplicate journal မဖြစ်ရ။

6. Posted entry ကို immutable ဖြစ်အောင် ကာကွယ်ရမယ်။

အရေးကြီးတာက database `CHECK` constraint တစ်ခုတည်းနဲ့ multiple posting rows ရဲ့ debit/credit total ကို မစစ်နိုင်တာပါ။ Transaction boundary, locking, controlled posting logic နဲ့ database permissions/triggers ကို ပေါင်းစပ်သုံးရမယ်။

### ဥပမာ — Customer A က Customer B ကို 10,000 MMK လွှဲခြင်း

| Ledger account              | Debit  | Credit |
| --------------------------- | ------ | ------ |
| Customer A wallet liability | 10,000 | —      |
| Customer B wallet liability | —      | 10,000 |
| Total                       | 10,000 | 10,000 |

Wallet တစ်ခုစီအတွက် ledger account ကို သီးခြားထားမယ်ဆိုရင် ဒီဥပမာလို စာရင်းသွင်းနိုင်တယ်။ Fee ပါလာရင် fee အတွက် additional postings တွေ ထည့်ရမယ်။

Deliverables: Migrations, account seeding, `LedgerPostingService`, immutable posting controls, ledger unit tests.

Done when: Balanced journal ကို post လုပ်နိုင်ပြီး unbalanced journal, wrong-currency posting, duplicate source posting တွေကို reject လုပ်နိုင်ရမယ်။


## Phase 03 — Wallet Balance & Internal Transfer

ရည်ရွယ်ချက်: User နှစ်ယောက်ကြား wallet transfer ကို concurrency ရှိနေချိန်မှာတောင် မှန်ကန်စွာ ဆောင်ရွက်နိုင်ရန်။

### Transfer flow

1. Validate request

Authentication · ownership · amount · currency · idempotency

2. Begin database transaction

Lock wallet rows in deterministic ID order

3. Check available balance

posted_balance − reserved_balance ≥ amount + fee

4. Post balanced ledger journal

Create transfer, journal and postings

5. Update balances + outbox

Commit everything atomically

6. After commit

Queue notifications and publish events

### Implementation tasks

* `TransferService`

* `WalletBalanceService`

* Wallet ownership policies

* Idempotency middleware/service

* Wallet row locking using `SELECT ... FOR UPDATE`

* Deterministic lock ordering to reduce deadlocks

* Transfer history indexes

* Retry handling for safe transient database errors

သတိပြုရန်: Balance update နဲ့ ledger posting ကို transaction တစ်ခုတည်းထဲမှာ လုပ်ရမယ်။ Notification ပို့တာကတော့ financial database transaction အတွင်းမှာ မလုပ်သင့်ဘူး။

Deliverables: Transfer API, balance API, transaction history API, concurrent-transfer tests.

Done when: တစ်ချိန်တည်းမှာ request အများကြီးလာလည်း available balance ထက်ပိုပြီး လွှဲမရ၊ retry လုပ်လည်း duplicate transfer မဖြစ်ရ။

## Phase 04 — Deposits

ရည်ရွယ်ချက်: Manual deposit နဲ့ payment provider deposit ကို တူညီတဲ့ financial posting rules အောက်မှာ စီမံရန်။

### Manual deposit flow

1. Customer က deposit request ဖန်တီးမယ်။

2. Finance operator က အထောက်အထားနဲ့ amount ကို စစ်ဆေးမယ်။

3. Approval ပြီးနောက် ငွေလက်ခံရရှိမှုကို အတည်ပြုမယ်။

4. သတ်မှတ်ထားတဲ့ policy အတိုင်း ledger journal post လုပ်ပြီး wallet balance တိုးမယ်။

5. Audit record နဲ့ notification ထုတ်မယ်။

Approval ရှိတာနဲ့ ငွေလက်ခံရရှိပြီးသားလို့ မယူဆရဘူး။ `approved` နဲ့ `succeeded` ရဲ့ အဓိပ္ပာယ်ကို business rules မှာ သီးခြားသတ်မှတ်ထားရမယ်။

### Provider deposit flow

1. Deposit intent ဖန်တီးမယ်။

2. Provider checkout/payment process ကို စတင်မယ်။

3. Webhook ကို လက်ခံပြီး signature စစ်မယ်။

4. `webhook_inbox` ထဲမှာ provider event ID ကို unique နဲ့ သိမ်းမယ်။

5. Provider reference, amount, currency, expected order နဲ့ payment status ကို verify လုပ်မယ်။

6. Valid ဖြစ်မှ ledger နဲ့ wallet balance ကို atomic transaction နဲ့ update လုပ်မယ်။

7. Duplicate webhook ဆိုရင် financial posting ထပ်မလုပ်ဘဲ safe response ပြန်ပေးမယ်။

### လိုအပ်တဲ့ services

* `DepositService`

* `ProviderWebhookService`

* `PaymentProviderInterface`

* Provider-specific adapters

* `WebhookInboxRepository`

* Provider signature verification

* Webhook retry/replay handling

Deliverables: Manual deposit APIs, provider adapter contract, webhook inbox, duplicate webhook tests.

Done when: Webhook တစ်ခုကို အကြိမ်များစွာပို့လည်း wallet credit တစ်ကြိမ်သာ ဖြစ်ရမယ်။ Invalid signature နဲ့ amount/currency mismatch တွေကို reject လုပ်ရမယ်။

## Phase 05 — Withdrawals

Withdrawal က transfer ထက် ပိုရှုပ်တယ်။ Customer balance ထဲက ငွေကို reserve လုပ်ထားပြီး provider/bank က အမှန်တကယ် payout ပြီးမပြီး မသေချာတဲ့အခြေအနေတွေကို ကိုင်တွယ်ရမယ်။

### Withdrawal state flow

`pending` — Request created

`reserved` — Funds held

`processing` — Payout initiated

`succeeded`

Confirm payout; consume hold

`reconciling`

Unknown result; keep hold

`failed` / `cancelled` ဖြစ်ပြီး payout မဖြစ်ကြောင်း အတည်ပြုပြီးမှ hold ကို release လုပ်မယ်။

### Implementation tasks

* `WithdrawalService`

* `WalletHoldService`

* `wallet_holds` table

* `reserved_balance` consistency checks

* Payout provider adapter

* Provider status inquiry

* Timeout handling and reconciliation

* Admin review/approval

* Hold expiration policy with safe state verification

Accounting rule: Hold လုပ်တာဟာ ငွေကို ချက်ချင်း ledger မှာ ထုတ်လိုက်တာမဟုတ်ဘူး။ Available balance ကို လျှော့ထားတာဖြစ်တယ်။ Payout အောင်မြင်မှ wallet liability လျှော့ပြီး payout clearing account သို့မဟုတ် သက်ဆိုင်ရာ account ကို post လုပ်မယ်။ Provider/bank settlement ရောက်လာတဲ့အခါ clearing ကို cash/bank နဲ့ reconcile လုပ်မယ်။

Deliverables: Withdrawal APIs, hold lifecycle, payout adapter, timeout recovery tests.

Done when: Double withdrawal မဖြစ်ရ၊ failed payout ကို မှားယွင်းပြီး hold release မလုပ်ရ၊ timeout ဖြစ်တဲ့ transaction ကို status မသိသေးဘဲ failed မသတ်မှတ်ရ။

## Phase 06 — Transaction History, Statements & Admin

### Customer APIs

* `GET /api/v1/wallets`

* `GET /api/v1/wallets/{wallet}/balance`

* `GET /api/v1/wallets/{wallet}/transactions`

* `GET /api/v1/transfers/{transfer}`

* `GET /api/v1/deposits`

* `GET /api/v1/withdrawals`

### Admin features

* Customer and wallet management

* Freeze/unfreeze wallet

* Deposit/withdrawal review

* Transfer and journal search

* Provider transaction lookup

* Reconciliation discrepancy review

* Audit history

* Role-based permission checks

### Important design decisions

* Transaction history ကို `transfers` table တစ်ခုတည်းမှ မထုတ်ပါနဲ့။ Deposits, withdrawals, fees, reversals တို့ကိုလည်း history ထဲမှာ ထည့်ရမယ်။

* Cursor pagination ကို timestamp တစ်ခုတည်းမဟုတ်ဘဲ stable ordering key နဲ့ သုံးပါ။

* Customer က ကိုယ့် wallet/resource ပဲ မြင်နိုင်ရမယ်။ URL ထဲက ID ပြောင်းပြီး အခြား user data ရယူလို့ မရစေရ။

* Posted ledger data ကို admin ကတောင် direct edit မလုပ်နိုင်ရဘူး။ Correction ကို controlled reversal workflow နဲ့ပဲ လုပ်ရမယ်။

* Statement ထုတ်တဲ့အခါ opening balance, movements, closing balance, currency နဲ့ time range ကို ရှင်းရှင်းလင်းလင်း ဖော်ပြရမယ်။

Deliverables: Customer transaction views, admin dashboard, policies, statement export, audit logs.

Done when: Authorization tests, pagination tests, statement total verification အားလုံး pass ဖြစ်ရမယ်။


## Phase 07 — Events, Transactional Outbox & PostgreSQL Partitioning

ဒီ Phase မှာ financial transaction ကို အောင်မြင်စွာ commit လုပ်ပြီးနောက် event publishing, notifications နဲ့ event storage ကို reliable ဖြစ်အောင် တည်ဆောက်မယ်။

### 7.1 Transactional outbox

`outbox_messages` table ကို financial data နဲ့အတူ database transaction ထဲမှာ insert လုပ်မယ်။

```
Database Transaction
  ├── Transfer
  ├── Journal Entry
  ├── Ledger Postings
  ├── Wallet Balance Updates
  └── Outbox Message
           │
         COMMIT
           │
           ▼
     Outbox Worker
           │
           ├── Queue notification
           ├── Publish application event
           └── Retry failed delivery
```

Outbox က application database commit နဲ့ event delivery ကြားမှာ ဖြစ်နိုင်တဲ့ failure ကို ကိုင်တွယ်ပေးတယ်။ ဒါပေမဲ့ consumer က duplicate event ကိုလည်း handle လုပ်နိုင်ဖို့ idempotent ဖြစ်ရမယ်။

### 7.2 Partition strategy

| Table                | Initial strategy        | အကြောင်းရင်း                              |
| -------------------- | ----------------------- | ----------------------------------------- |
| `payment_events`     | Monthly RANGE           | High-volume append-only events            |
| `audit_events`       | Monthly RANGE           | Audit history growth                      |
| `webhook_deliveries` | Monthly RANGE           | Provider delivery attempts                |
| `webhook_inbox`      | Unpartitioned initially | Global provider-event deduplication       |
| `outbox_messages`    | Unpartitioned initially | Mutable retry/processing state            |
| `journal_entries`    | Unpartitioned initially | Simpler financial integrity and queries   |
| `ledger_postings`    | Unpartitioned initially | Benchmark မလုပ်မချင်း complexity မတိုးရန် |

Partitioning ကို `occurred_at` / `created_at` ကဲ့သို့ timestamp column အလိုက် monthly range ခွဲမယ်။

ဥပမာ `payment_events` အတွက်—

SQL

```
CREATE TABLE payment_events (
    id          uuid        NOT NULL,
    aggregate_type varchar(50) NOT NULL,
    aggregate_id uuid        NOT NULL,
    event_type   varchar(100) NOT NULL,
    occurred_at  timestamptz NOT NULL,
    payload      jsonb       NOT NULL DEFAULT '{}',
    PRIMARY KEY (id, occurred_at)
) PARTITION BY RANGE (occurred_at);
```

Partition တစ်ခုချင်းစီရဲ့ boundaries တွေကို `FOR VALUES FROM (...) TO (...)` နဲ့ သတ်မှတ်ရမယ်။ Monthly partitions တွေကို အလိုအလျောက် ကြိုတင်ဖန်တီးပြီး missing partition alerts ထားမယ်။

သတိပြုရန်: Partitioned table မှာ partition key မပါဝင်တဲ့ global unique constraint ကို ပုံမှန်အားဖြင့် သတ်မှတ်လို့မရပါဘူး။ Event ID ကို globally unique ဖြစ်စေချင်ရင် unpartitioned registry သို့မဟုတ် သင့်တော်တဲ့ uniqueness strategy တစ်ခု ထပ်လိုနိုင်တယ်။

### 7.3 Partition implementation tasks

* `PartitionManager` service/command

* Next N months အတွက် partitions ကြိုတင်ဖန်တီးခြင်း

* Missing partition health check

* Partition-aware indexes

* Retention policy

* Partition pruning query tests

* Event volume / query latency benchmarks

Deliverables: Outbox worker, event tables, partition automation, monitoring alerts.

Done when: Partition အသစ်တွေ အလိုအလျောက်ဖန်တီးနိုင်ရမယ်၊ timestamp filter ပါတဲ့ query တွေမှာ partition pruning အလုပ်လုပ်ရမယ်၊ worker restart ဖြစ်လည်း event delivery ပြန်လည်ဆက်လုပ်နိုင်ရမယ်။

## Phase 08 — Reconciliation & Financial Recovery

ရည်ရွယ်ချက်: System ထဲက balance, ledger နဲ့ external payment provider records တွေ မကိုက်ညီတဲ့အခါ ရှာဖွေ၊ စစ်ဆေး၊ ပြန်လည်ဖြေရှင်းနိုင်ဖို့။

### Reconciliation layers

1. Wallet reconciliation

Ledger-derived wallet balance နဲ့ `wallet_balances.posted_balance` ကို နှိုင်းယှဉ်မယ်။ Active holds စုစုပေါင်းနဲ့ `reserved_balance` ကိုလည်း စစ်မယ်။

2. Provider reconciliation

Deposit, payout, provider transaction references နဲ့ provider settlement records တွေကို နှိုင်းယှဉ်မယ်။

3. Bank settlement reconciliation

Provider clearing accounts နဲ့ အမှန်တကယ် bank statement/settlement amounts ကို match လုပ်မယ်။

### Additional tables

* `reconciliation_runs`

* `reconciliation_items`

* `provider_settlements`

* `provider_settlement_lines`

### Implementation tasks

* Scheduled reconciliation commands

* Mismatch classification — missing, duplicate, amount mismatch, status mismatch

* `reconciling` transaction recovery workflow

* Admin review and resolution records

* Reversal/correction service

* Alerting for unresolved discrepancies

အရေးကြီးချက်: Discrepancy တွေ့တာနဲ့ balance ကို အလိုအလျောက်ပြင်မပစ်ပါနဲ့။ ဘာကြောင့် မကိုက်တာလဲဆိုတာ ရှာဖွေပြီး approved correction workflow နဲ့ပဲ ဖြေရှင်းရမယ်။

Deliverables: Reconciliation jobs, discrepancy reports, manual resolution workflow, recovery runbooks.

Done when: Deliberately ထည့်ထားတဲ့ mismatch တွေကို ရှာဖွေတွေ့ရှိနိုင်ရမယ်၊ resolution ကို audit trail နဲ့ ပြန်လည်စစ်ဆေးနိုင်ရမယ်။

## Phase 09 — Security, Reliability & Performance

### Security

* Authentication, authorization, ownership checks

* Admin MFA အတွက် design/implementation

* Rate limiting

* Secrets management

* Webhook signature verification

* Replay/duplicate protection

* Sensitive data redaction

* Audit log access control

* Dependency/security scanning

### Reliability

* Deadlock and transaction retry strategy

* Queue retry, backoff, failed-job handling

* Provider timeout recovery

* Database connection pool/connection limits

* Redis outage handling

* Graceful shutdown and worker recovery

### Performance tests

| Test                             | ဘာကိုသိချင်တာလဲ                        |
| -------------------------------- | -------------------------------------- |
| Concurrent same-wallet transfers | Overspending / race conditions ရှိမရှိ |
| Same idempotency key replay      | Duplicate posting ဖြစ်မဖြစ်            |
| Hot-wallet contention            | Row-lock bottleneck                    |
| Ledger history queries           | Indexing/query latency                 |
| Large event-table scans          | Partition pruning အလုပ်လုပ်မလုပ်       |
| Outbox backlog                   | Worker throughput နဲ့ recovery time    |
| Reconciliation on large datasets | Runtime, locks, DB load                |

### Ledger partitioning ကို ဘယ်အချိန်မှာ ဆုံးဖြတ်မလဲ?

Ledger tables ကို အစကတည်းက partition မလုပ်ဘဲ realistic dataset နဲ့ benchmark လုပ်မယ်။ Query latency, index size, write throughput, retention requirements နဲ့ operational complexity ကိုတိုင်းတာပြီးမှ ဆုံးဖြတ်မယ်။

Ledger partitioning လုပ်မယ်ဆိုရင် journal/posting relationships, cross-partition queries, uniqueness, FK constraints နဲ့ accounting-period queries ကို သီးခြား design review လုပ်ရမယ်။

Deliverables: Load-test report, threat review, performance baselines, reliability runbooks.

Done when: သတ်မှတ်ထားတဲ့ workload အောက်မှာ correctness tests pass ဖြစ်ပြီး performance targets ကို တိုင်းတာနိုင်ရမယ်။

## Phase 10 — Deployment & Operations

Production မတင်ခင် အောက်ပါ operational checklist ကို ဖြည့်ဆည်းမယ်။

* Separate development, staging, production environments

* Database migrations and rollback/recovery plan

* TLS, secrets, least-privilege database roles

* Redis queues and worker supervision

* Scheduled jobs and partition creation monitoring

* Application, database, queue, provider metrics

* Centralized structured logs and correlation IDs

* Alerts for failed payouts, webhook backlog, reconciliation mismatch

* Automated backups

* Restore test on a separate environment

* Incident response and manual recovery runbooks

* Production smoke tests and staged rollout

Done when: Database restore ကို လက်တွေ့စမ်းသပ်ပြီးပြီ၊ alert တွေကို စမ်းသပ်ပြီးပြီ၊ provider timeout နဲ့ duplicate webhook recovery ကို staging မှာ end-to-end စမ်းသပ်ပြီးပြီ။


## 3. Database schema ကို တည်ဆောက်ရာမှာ ပြင်ဆင်ထားရမယ့်အချက်များ

လက်ရှိ draft schema ကို implementation မစခင် အောက်ပါအချက်တွေ ပြင်ဆင်ဖို့လိုတယ်။

| အချက်                       | Implementation decision                                                                                           |
| --------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Wallet/currency consistency | `wallets (id, currency_code)` composite unique key နဲ့ transfer/deposit/withdrawal composite foreign keys သုံးရန် |
| Idempotency                 | Scope + key + request hash နဲ့ centrally manage လုပ်ရန်                                                           |
| Ledger immutability         | Posted journal/postings ကို update/delete မလုပ်နိုင်အောင် ကာကွယ်ရန်                                               |
| Reserved balance            | Active holds စုစုပေါင်းနဲ့ balance cache ကို transaction အတွင်း synchronize လုပ်ရန်                               |
| Provider references         | Provider ID ကို foreign key ချိတ်ရန်၊ provider reference uniqueness ကို provider အလိုက်သတ်မှတ်ရန်                 |
| Multi-currency              | Amount ကို minor units အဖြစ်သိမ်းပြီး journal တစ်ခုအတွင်း currency မရောရန်                                        |
| Financial corrections       | Direct update မလုပ်ဘဲ reversal/correction entries သုံးရန်                                                         |
| Events                      | Event partitioning နဲ့ global deduplication ကို သီးခြားစီ design လုပ်ရန်                                          |

ဒီအချက်တွေကို migration ရေးပြီးမှ ပြင်တာထက် Phase 00–02 အတွင်း design review လုပ်ပြီး ပြင်ဆင်ထားတာ ပိုကောင်းတယ်။

## 4. Recommended implementation milestones

## Milestone tracker

3 / 5 complete

M1 — Foundation — ✅ complete

Phase 00–02

• Business rules approved

• Auth and module foundation

• Ledger posting tests pass

M2 — Core money movement — ✅ complete

Phase 03–05

• Internal transfer is atomic

• Deposit deduplication works

• Withdrawal holds and timeout recovery work

M3 — User and admin workflows — ✅ complete

Phase 06

• Transaction history and statements

• Authorization and audit coverage

M4 — Scale and correctness

Phase 07–09

• Outbox and partition automation

• Reconciliation workflows

• Concurrency and load tests

M5 — Production readiness

Phase 10

• Monitoring and alerts

• Backup restore verified

• Incident runbooks tested

ဒီ checklist က ကိုယ်တိုင် progress မှတ်သားဖို့အတွက်ပါ။ Project database ထဲက implementation status ကို အလိုအလျောက်ဖတ်ပြတာ မဟုတ်ပါဘူး။

## 5. AI coding agent နဲ့ implementation လုပ်မယ့် workflow

Copilot သို့မဟုတ် local coding agent ကို အသုံးပြုမယ်ဆိုရင် Phase တစ်ခုလုံးကို တစ်ခါတည်း code ရေးခိုင်းတာထက် အောက်ပါ workflow နဲ့သွားပါ။

1. Analyze — လက်ရှိ project structure နဲ့ existing conventions ကို စစ်ဆေးခိုင်းပါ။

2. Design — Schema, interfaces, state transitions, transaction boundaries ကို review လုပ်ခိုင်းပါ။

3. Implement — သတ်မှတ်ထားတဲ့ scope ထဲက feature တစ်ခုကိုသာ ရေးခိုင်းပါ။

4. Test — Unit, feature, concurrency, idempotency tests ထည့်ခိုင်းပါ။

5. Review — Race conditions, authorization, currency consistency, ledger imbalance နဲ့ failure recovery ကို စစ်ခိုင်းပါ။

6. Verify — Tests run လုပ်ပြီး output အပေါ်အခြေခံကာ နောက် Phase ကို ဆက်ပါ။

Financial code အတွက် “tests pass ဖြစ်တယ်” ဆိုတာနဲ့ မလုံလောက်ပါဘူး။ Test တွေက အရေးကြီးတဲ့ financial invariants တွေကို တကယ်စစ်ဆေးထားသလားဆိုတာပါ review လုပ်ရမယ်။

## 6. ဘယ်ကနေ စရမလဲ?

ကျွန်တော့်အကြံပြုချက်က Phase 00 ကို အရင်ပြီးစီးအောင်လုပ်ပြီး Phase 01–02 ကို ဆက်သွားပါ။ အထူးသဖြင့် `LedgerPostingService`, idempotency model, wallet locking နဲ့ accounting rules တွေကို မတည်ဆောက်ခင် သေချာသတ်မှတ်ထားဖို့လိုတယ်။

အဲဒီ foundation ပြီးသွားမှ transfer, deposit, withdrawal တို့ကို ဆက်တည်ဆောက်ရင် financial correctness ကို feature တစ်ခုချင်းစီမှာ ပြန်စဉ်းစားနေရတာ နည်းသွားမယ်။
