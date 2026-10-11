# Digital Wallet & Financial Ledger Platform — Complete Implementation Blueprint

ဒီတစ်ခေါက် Blueprint ကို Backend, Frontend, Business Logic, UI/UX Design, Database, Security, Testing နဲ့ Deployment အားလုံးပါဝင်အောင် အစအဆုံး ပြန်လည်ဖွဲ့စည်းမယ်။

အရေးကြီးတဲ့ ပြင်ဆင်ချက် ၂ ခုရှိတယ်။

1. Frontend ကို Backend ပြီးမှလုပ်မယ့် နောက်ဆုံး Phase အဖြစ် မထားတော့ဘူး။ Foundation တည်ဆောက်ချိန်ကတည်းက Frontend architecture, design system, API contract နဲ့ UI implementation ကို Backend နဲ့အပြိုင် တိုးတက်အောင်လုပ်မယ်။

2. “ISO standard ဖြစ်ရမယ်” ဆိုတာကို တိတိကျကျ သတ်မှတ်မယ်။ ISO standard တစ်ခုတည်းက Backend code, business logic, database schema နဲ့ UI design အားလုံးကို တစ်ခါတည်း စံသတ်မှတ်ပေးတာ မဟုတ်ပါဘူး။ သက်ဆိုင်ရာ standards တွေကို project requirements, coding rules, testing evidence နဲ့ review process ထဲမှာ ထည့်သွင်းပြီး လိုက်နာမယ်။ ISO certification ရထားတယ်လို့တော့ သီးခြား audit မလုပ်ဘဲ မဆိုနိုင်ပါဘူး။

## 1. Project scope & technical decisions

## Digital Wallet & Financial Ledger Platform

Single platform · Multi-currency · Customer + Admin portals

Backend

Laravel + PHP

Frontend

Vue 3 + TypeScript

Database

PostgreSQL

Cache / Queue

Redis

Realtime

Laravel Reverb + Laravel Echo (WebSockets)

Frontend state

Pinia + Vue Router

UI

Tailwind CSS + shadcn-vue patterns

Architecture decisions

* Modular Monolith — အစပိုင်းမှာ Microservices မခွဲသေးပါ။

* Double-Entry Ledger — financial record of truth။

* Cached Wallet Balance — မြန်မြန်ဖတ်နိုင်ဖို့၊ reconciliation နဲ့စစ်ဆေးမယ်။

* Hybrid Partitioning — event tables ကို partition လုပ်မယ်၊ ledger partitioning ကို benchmark ပြီးမှဆုံးဖြတ်မယ်။

* API-first — Backend/Frontend ကို OpenAPI contract အပေါ် အခြေခံပြီး parallel implement လုပ်မယ်။

### MVP business scope

* User authentication, roles and permissions

* Multi-currency wallets

* Internal wallet-to-wallet transfers

* Manual and payment-provider deposits

* Manual and payment-provider withdrawals

* Withdrawal holds / reserved balance

* Double-entry ledger and immutable postings

* Idempotency and webhook deduplication

* Customer transaction history and statements

* Admin dashboard and transaction review

* Audit trail, transactional outbox and notifications

* Reconciliation, event partitioning and operational monitoring

MVP အတွင်း FX exchange, lending, crypto, card acquiring နဲ့ cross-border settlement ကို မထည့်သေးပါ။ Multi-currency wallet support ရှိခြင်းက currency conversion support ရှိခြင်းနဲ့ မတူပါဘူး။


## 2. ISO standards နဲ့ project compliance framework

ဒီ project အတွက် ISO standards ကို အောက်ပါအတိုင်း role ခွဲသုံးမယ်။

| Standard                | Project ထဲမှာ အသုံးချမယ့်အပိုင်း                                                                                                                                                             |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ISO/IEC/IEEE 12207:2026 | Software lifecycle, development, operation, maintenance process<br>![](https://www.google.com/s2/favicons?domain=https://www.iso.org\&sz=32)<br>Software life cycle processes                |
| ISO/IEC/IEEE 29148:2018 | Requirements engineering, testable requirements, traceability<br>![](https://www.google.com/s2/favicons?domain=https://www.iso.org\&sz=32)<br>Requirements engineering                       |
| ISO/IEC 25010:2023      | Software quality model, maintainability, reliability, security, performance evaluation<br>![](https://www.google.com/s2/favicons?domain=https://www.iso.org\&sz=32)<br>Product quality model |
| ISO/IEC 27001:2022      | Information security risk management and ISMS requirements<br>![](https://www.google.com/s2/favicons?domain=https://www.iso.org\&sz=32)<br>Information security management systems           |
| ISO 9241-210:2019       | Human-centred UI/UX design and usability evaluation<br>![](https://www.google.com/s2/favicons?domain=https://committee.iso.org\&sz=32)<br>ISO                                                |
| ISO/IEC 40500:2025      | Web accessibility through WCAG 2.2 criteria<br>![](https://www.google.com/s2/favicons?domain=https://www.iso.org\&sz=32)<br>ISO                                                              |

ဒီ standards တွေက မတူညီတဲ့ရည်ရွယ်ချက်တွေရှိတယ်။ ဥပမာ ISO/IEC 27001 က coding style guide မဟုတ်ဘဲ information security management system အတွက် standard ဖြစ်တယ်။ ISO 9241-210 က user interface ကို လှပအောင်လုပ်ဖို့တစ်ခုတည်း မဟုတ်ဘဲ user needs, usability နဲ့ human-centred design process ကို အလေးထားတယ်။

### Project compliance model

ကျွန်တော်တို့ လက်တွေ့လုပ်ဆောင်မယ့်ပုံစံက —

```
ISO / Industry Standards
          │
          ▼
Project Policies & Quality Requirements
          │
          ▼
Architecture / Business / UI Specifications
          │
          ▼
Implementation Rules + Automated Tests
          │
          ▼
Code Review + QA Evidence
          │
          ▼
Release Approval + Audit Records
```

Project ထဲမှာ `compliance/` directory ထားပြီး standard တစ်ခုချင်းစီနဲ့ ချိတ်ဆက်ထားတဲ့ requirement, implementation evidence, test results နဲ့ review records တွေကို ထိန်းသိမ်းမယ်။

ဥပမာ — “Transfer တစ်ခုဟာ duplicate request ကြောင့် နှစ်ကြိမ် credit မဖြစ်ရ” ဆိုတဲ့ requirement အတွက် business rule, API behavior, database constraint, automated test နဲ့ code review evidence ကို တစ်နေရာနဲ့တစ်နေရာ ချိတ်ဆက်ထားမယ်။

## 3. Complete phase roadmap — Backend + Frontend + UI/UX

Frontend ကို phase တစ်ခုတည်းထဲ ထည့်မထားဘဲ အောက်ပါ roadmap အတိုင်း Backend နဲ့ အပြိုင် implement လုပ်မယ်။

### 00

Requirements & Compliance

Business requirements, ISO mapping, acceptance criteria

### 01

System Architecture & API Contract

Domain boundaries, OpenAPI, database and integration contracts

### 02

Backend & Frontend Foundations

Laravel modules, Vue app shell, CI, linting, test setup

### 03

UI/UX Research & Design System

User journeys, wireframes, tokens, reusable components, accessibility

### 04

Identity, Authentication & RBAC

Login, session/token strategy, route guards, permission-aware UI

### 05

Database & Ledger Core

Currencies, wallets, journal entries, postings, ledger invariants

### 06

Wallet Dashboard & Balance UI

Wallet list, currency selection, balance and empty/error states

### 07

Internal Transfer

Atomic backend transfer, confirmation UI, receipts, transaction status

### 08

Deposit

Manual/provider deposits, payment status, webhook processing

### 09

Withdrawal

Available balance, holds, payout lifecycle and unknown outcomes

### 10

History, Statements & Admin Portal

Search, filters, pagination, approvals, audit and exports

### 11

Outbox, Events & Partitioning

Reliable delivery, event storage, partition automation

### 12

Reconciliation & Recovery

Balance/provider matching, discrepancies and controlled corrections

### 13

Security, Accessibility & Quality Assurance

Threat tests, WCAG checks, end-to-end and performance tests

### 14

Deployment & Operations

Staging, production, monitoring, backup and incident response

အရေးကြီးတဲ့ principle က Phase တစ်ခုချင်းစီမှာ Backend API, Frontend screens, tests နဲ့ acceptance criteria တွေကို တစ်တွဲတည်း သတ်မှတ်ထားဖို့ပါ။ Phase 03 မှာ design system ကို ကြိုတင်ပြင်ဆင်ထားပေမယ့် actual screens တွေကို feature phase တွေမှာ တဖြည်းဖြည်း implement လုပ်မယ်။

# Part A — Requirements & architecture

## Phase 00 — Requirements, business rules & compliance

ရည်ရွယ်ချက်: Business, Backend, Frontend နဲ့ UI/UX တို့အတွက် shared source of truth တစ်ခု တည်ဆောက်ရန်။

### Backend/business tasks

* User roles — `customer`, `admin`, `finance_operator`, `auditor`

* Wallet, transfer, deposit, withdrawal state machines

* Multi-currency and minor-unit rules

* Double-entry accounting policies

* Idempotency, concurrency, reversal and reconciliation policies

* Provider timeout, retry, duplicate webhook behavior

* API error codes, pagination and filtering conventions

* Security and audit requirements

### Frontend/UI tasks

* Customer portal နဲ့ Admin portal ရဲ့ responsibilities သတ်မှတ်ရန်

* User journeys — login, wallet view, transfer, deposit, withdrawal, history

* Admin journeys — review, approve, reject, freeze, investigate

* Responsive layout requirements

* Accessibility and usability requirements

* Loading, empty, success, validation, error, pending, timeout states

* Burmese/English labels လိုအပ်မလား၊ currency/number/date format policy

### Deliverables

```
docs/
├── requirements/
│   ├── business-requirements.md
│   ├── functional-requirements.md
│   ├── non-functional-requirements.md
│   └── acceptance-criteria.md
├── business/
│   ├── accounting-rules.md
│   ├── transaction-state-machines.md
│   └── permissions-matrix.md
├── architecture/
│   ├── system-context.md
│   └── architecture-decisions/
└── compliance/
    ├── standards-mapping.md
    ├── requirements-traceability.md
    └── risk-register.md
```

Acceptance criteria: Feature တစ်ခုချင်းစီမှာ requirement ID, business rule, permission, expected API behavior, UI state နဲ့ test case တွေ ရှိရမယ်။

## Phase 01 — System architecture & API contract

ဒီ Phase မှာ Backend/Frontend ကို သီးခြားစီ စမ်းသပ်နိုင်ဖို့ contract-first approach သုံးမယ်။

### System architecture

Vue 3 Customer / Admin Portals

Pages · Components · Pinia · Router

API Contract

OpenAPI · Validation schema · Error format · Pagination

Laravel Modular Monolith

Identity · Wallets · Ledger · Transfers · Deposits · Withdrawals

PostgreSQL

Redis / Queue

Laravel Reverb (WebSockets)

Payment Providers

### API contract rules

* API versioning — `/api/v1/...`

* JSON response format တစ်သမတ်တည်း

* Error codes နဲ့ validation errors သတ်မှတ်ထားခြင်း

* Cursor pagination, filter/sort allowlist

* Amount ကို minor units နဲ့ သယ်ဆောင်ခြင်း

* Currency code ကို အမြဲ explicit ထည့်ပေးခြင်း

* Money movement requests အတွက် idempotency key

* OpenAPI schema ကို API tests နဲ့ စစ်ဆေးခြင်း

ဥပမာ response contract —

JSON

```
{
  "data": {
    "id": "transfer-id",
    "amount_minor": 10000,
    "currency": "MMK",
    "status": "succeeded"
  },
  "meta": {
    "request_id": "request-id"
  }
}
```

ဒါက illustrative example ဖြစ်ပြီး production schema မှာ ID format, timestamps, error contract နဲ့ money representation ကို သီးခြားသတ်မှတ်မယ်။

### Deliverables

* System context diagram

* Domain model and ERD

* API OpenAPI specification

* ADRs (Architecture Decision Records)

* API error and pagination standards

* Backend/Frontend integration test plan

Acceptance criteria: Frontend team က API implementation မပြီးသေးခင် OpenAPI contract နဲ့ mock responses ကို အသုံးပြုပြီး screens တည်ဆောက်နိုင်ရမယ်။

# Part B — Backend foundation

## Phase 02 — Backend & Frontend foundations

### Backend foundation

```
app/Modules/
├── Identity/
├── Wallets/
├── Ledger/
├── Transfers/
├── Deposits/
├── Withdrawals/
├── PaymentProviders/
├── Reconciliation/
├── Audit/
└── Shared/
```

Module တစ်ခုချင်းစီမှာ လိုအပ်သလို `Controllers`, `Requests`, `DTOs`, `Services`, `Repositories/Contracts`, `Repositories/Eloquent`, `Models`, `Enums`, `Policies`, `Tests` ကို ထားမယ်။

Backend coding rules:

* Controller က HTTP concerns ကိုသာ ကိုင်တွယ်မယ်။

* FormRequest က request validation လုပ်မယ်။

* DTO က typed input/output data ကို ကိုယ်စားပြုမယ်။

* Service/Domain layer က business rules ကို enforce လုပ်မယ်။

* Repository က လိုအပ်တဲ့ data access abstraction ကိုပဲ ကိုင်တွယ်မယ်။

* Interface bindings ကို service provider မှာ register လုပ်မယ်။

* Financial transaction boundary ကို service layer မှာ ရှင်းရှင်းလင်းလင်း သတ်မှတ်မယ်။

* Domain logic ကို Eloquent model callbacks တွေထဲမှာ ပျံ့နှံ့မသွားစေရ။

### Frontend foundation

```
src/
├── app/
│   ├── router/
│   ├── layouts/
│   └── providers/
├── components/
│   ├── ui/
│   └── shared/
├── features/
│   ├── auth/
│   ├── wallets/
│   ├── transfers/
│   ├── deposits/
│   ├── withdrawals/
│   ├── transactions/
│   └── admin/
├── services/
│   ├── api/
│   └── generated/
├── stores/
├── types/
├── utils/
└── tests/
```

Frontend coding rules:

* Vue 3 Composition API + TypeScript strict mode

* `<script setup lang="ts">` convention

* Pinia ကို cross-page/shared state အတွက်သာ သင့်တော်သလိုသုံးမယ်။

* API calls ကို component အတွင်း တိုက်ရိုက်မရေးဘဲ feature service/query layer ကတစ်ဆင့် ခေါ်မယ်။

* Backend validation rules ကို Frontend က ထပ်တူရေးထားရုံနဲ့ security boundary ဖြစ်တယ်လို့ မယူဆရ။

* Route guards က UX အတွက်ဖြစ်ပြီး authorization ကို Backend မှာ ထပ်စစ်ရမယ်။

* Money values ကို JavaScript floating-point arithmetic နဲ့ မတွက်ရ။ Integer minor units သို့မဟုတ် exact decimal strategy သုံးမယ်။

* Server state နဲ့ UI-only state ကို သီးခြားစီ ခွဲထားမယ်။

### CI quality gates

* PHP formatting, static analysis, tests

* TypeScript typecheck, ESLint, formatting

* Vue component/unit tests

* OpenAPI schema validation

* Dependency/security scanning

* Migration and seed verification

* Build succeeds before merge

Acceptance criteria: Backend/Frontend နှစ်ဖက်လုံး local development, lint, typecheck/test, CI workflow အဆင်သင့်ဖြစ်ရမယ်။

# Part C — Frontend design system & UI/UX

## Phase 03 — UI/UX research, design system & component standards

ဒီ Phase ကို Frontend implementation မစခင် လုပ်မယ်။ Design system မရှိဘဲ page တစ်ခုချင်းစီမှာ ကိုယ်ပိုင် button, modal, table, form စတာတွေ ရေးသွားရင် consistency မရှိတော့ဘူး။

### 3.1 UI reference and component policy

UI component reference အနေနဲ့ shadcn design principles နဲ့ component flows ကို သုံးမယ်။ Vue implementation အတွက် React component code ကို တိုက်ရိုက်ကူးမသုံးဘဲ shadcn-vue-compatible Vue components ကို သုံးမယ်။

Reference: [shadcn-vue documentation](https://www.shadcn-vue.com/) · [shadcn/ui component reference](https://ui.shadcn.com/docs/components)

Tailwind CSS နဲ့ component primitives တွေကို project ရဲ့ design tokens အပေါ် အခြေခံပြီး တစ်သမတ်တည်း သုံးမယ်။

### 3.2 Design system layers

#### 1

Design tokens

Colors, typography, spacing, radius, shadows, focus rings, breakpoints

#### 2

Primitive components

Button, Input, Label, Select, Checkbox, Dialog, Popover, Tooltip

#### 3

Composite components

MoneyInput, CurrencySelect, StatusBadge, ConfirmTransferDialog, DataTable

#### 4

Feature components

WalletBalanceCard, TransferForm, DepositStatusPanel, WithdrawalReviewPanel

#### 5

Pages and layouts

Dashboard, Wallets, Transfer, History, Admin Operations

### 3.3 Mandatory reusable UI components

| Category   | Components                                                                     |
| ---------- | ------------------------------------------------------------------------------ |
| Form       | `FormField`, `MoneyInput`, `CurrencySelect`, `DateRangePicker`, `SearchInput`  |
| Feedback   | `Alert`, `Toast`, `InlineError`, `Skeleton`, `EmptyState`, `RetryPanel`        |
| Navigation | `AppSidebar`, `Topbar`, `Breadcrumbs`, `MobileNav`                             |
| Data       | `DataTable`, `Pagination`, `FilterBar`, `AmountDisplay`, `StatusBadge`         |
| Dialog     | `ConfirmDialog`, `TransferReviewDialog`, `ApproveRequestDialog`                |
| Financial  | `WalletBalanceCard`, `TransactionRow`, `TransactionTimeline`, `ReceiptPanel`   |
| Admin      | `PermissionGate`, `AuditTimeline`, `ReviewPanel`, `ReconciliationMismatchCard` |

Financial UI component တွေက formatting ပဲ လုပ်ရမယ်။ ဥပမာ `AmountDisplay` က money value ကို display လုပ်ပေးပေမယ့် transfer balance ကို တွက်ချက်ဆုံးဖြတ်တာမျိုး မလုပ်ရဘူး။

### 3.4 UI design rules

* Semantic HTML elements နဲ့ keyboard navigation ကို မှန်ကန်စွာ သုံးမယ်။

* Color တစ်ခုတည်းနဲ့ success/failure/pending ကို မဖော်ပြရ။ Text/icon ပါရမယ်။

* Focus state, validation message နဲ့ error recovery action တွေ ရှိရမယ်။

* Desktop table တွေကို mobile မှာ အလျားလိုက်မလွန်ကဲစေဘဲ responsive table/card pattern သတ်မှတ်မယ်။

* Form labels, descriptions, required state နဲ့ accessible error messages ထည့်မယ်။

* Confirm action များမှာ amount, currency, destination ကို ပြန်လည်စစ်ဆေးနိုင်အောင် ပြမယ်။

* Dark/light theme ထောက်ပံ့မယ်ဆိုရင် contrast နဲ့ focus states ကို theme နှစ်မျိုးလုံးမှာ စမ်းသပ်မယ်။

* UI states အားလုံးကို သတ်မှတ်မယ် — loading, empty, error, disabled, pending, success, timeout, retry.

### 3.5 Accessibility and usability

ISO 9241-210 ရဲ့ human-centred design approach နဲ့ ISO/IEC 40500:2025 (WCAG 2.2) criteria ကို လမ်းညွှန်အဖြစ်သုံးမယ်။

![](https://www.google.com/s2/favicons?domain=https://committee.iso.org\&sz=32)

ISO

+1

လိုအပ်တဲ့ checks:

* Keyboard-only navigation

* Visible focus

* Accessible form labels

* Contrast checks

* Dialog focus management

* Screen-reader accessible status updates

* Error messages that explain how to recover

* Responsive zoom/reflow checks

Deliverables: Design tokens, component inventory, component usage guide, page wireframes, UI state matrix, accessibility checklist.

Acceptance criteria: Feature page အသစ်တစ်ခု တည်ဆောက်တဲ့အခါ custom primitive အသစ်တွေ မလိုအပ်ဘဲ shared components တွေကို ပြန်အသုံးပြုနိုင်ရမယ်။ New component တစ်ခုထည့်ရင် usage guide နဲ့ tests ပါရမယ်။


# Part D — Authentication, database & financial business logic

## Phase 04 — Identity, authentication & RBAC

### Backend

* User authentication and session/token strategy

* Password reset and email verification if required

* Roles, permissions and policies

* Admin MFA

* Login rate limiting

* Session revocation and logout

* Audit logging for sensitive administrative actions

### Frontend

* Login, logout, password reset pages

* Protected routes

* Permission-aware navigation and actions

* Session expiry handling

* Unauthorized and forbidden pages

* Form validation and server error display

* Accessible password visibility controls

### Business rules

* Frontend မှာ button ကို hide လုပ်ထားတာက authorization မဟုတ်ဘူး။

* Backend က resource ownership နဲ့ permission ကို request တစ်ခုချင်းစီမှာ စစ်ရမယ်။

* Admin က wallet freeze လုပ်နိုင်ပေမယ့် posted journal ကို direct edit/delete မလုပ်နိုင်ရ။

* Session expired ဖြစ်ရင် unsaved financial form data ကို ဘယ်လိုကိုင်တွယ်မလဲ သတ်မှတ်ရမယ်။

Acceptance criteria: Customer က အခြား customer ရဲ့ wallet ကို ID ပြောင်းပြီး ဝင်ကြည့်မရရ။ UI route ကို တိုက်ရိုက်ခေါ်တာနဲ့ Backend API က unauthorized access ကို reject လုပ်ရမယ်။

## Phase 05 — Database & Ledger Core

### Database implementation order

| Order | Tables                       | Purpose                         |
| ----- | ---------------------------- | ------------------------------- |
| 1     | `users`, roles, permissions  | Identity and access             |
| 2     | `currencies`                 | Currency metadata               |
| 3     | `wallets`, `wallet_balances` | Wallet and cached balances      |
| 4     | `ledger_accounts`            | Chart of accounts               |
| 5     | `journal_entries`            | Financial transaction headers   |
| 6     | `ledger_postings`            | Debit/credit entries            |
| 7     | `idempotency_keys`           | Duplicate request prevention    |
| 8     | `audit_events`               | Administrative/security history |

### Ledger business rules

* Currency တစ်ခုချင်းစီအလိုက် journal တစ်ခုစီမှာ total debit = total credit ဖြစ်ရမယ်။

* Amount ကို integer minor units (`bigint`) နဲ့ သိမ်းမယ်။

* Journal နဲ့ account currency မကိုက်ရင် reject လုပ်မယ်။

* Posted journals နဲ့ postings တွေကို immutable ဖြစ်အောင် ကာကွယ်မယ်။

* Correction ကို reversal entry အသစ်နဲ့ လုပ်မယ်။

* Duplicate source transaction အတွက် duplicate journal မဖြစ်ရ။

* Ledger posting, cached balance update နဲ့ outbox insertion ကို သက်ဆိုင်ရာ financial transaction တစ်ခုအတွင်း atomic ဖြစ်စေရမယ်။

### Critical service

PHP

```
interface LedgerPostingServiceInterface
{
    public function post(
        JournalEntryData $entry
    ): PostedJournalResult;
}
```

ဒီ interface က architectural example ဖြစ်တယ်။ DTO နဲ့ result class တွေကို project ထဲမှာ သီးခြား define လုပ်ရမယ်။

### Schema design review requirements

* `wallets (id, currency_code)` ကို composite unique key သတ်မှတ်ပြီး transfer/deposit/withdrawal မှာ wallet-currency consistency ကို composite foreign key နဲ့ ကာကွယ်ရန်။

* `ledger_accounts` မှာ currency strategy ကို explicit သတ်မှတ်ရန်။

* Journal posting totals ကို application logic/controlled database procedure နဲ့ validate လုပ်ရန်။

* Posted data ကိုသာမန် application DB role က update/delete မလုပ်နိုင်အောင် permission/triggers စဉ်းစားရန်။

* Global idempotency key uniqueness ကို `scope + key` အလိုက် သတ်မှတ်ရန်။

* Active holds နဲ့ `reserved_balance` ကို atomic transaction အတွင်း synchronize လုပ်ရန်။

### Frontend work in this phase

Ledger table ကို customer UI မှာ တိုက်ရိုက်ဖော်ပြဖို့ မလိုအပ်ပါဘူး။ ဒါပေမဲ့ Backend က ထုတ်ပေးမယ့် financial states တွေအပေါ် အခြေခံပြီး frontend types, API client နဲ့ shared amount formatting utilities ကို တည်ဆောက်မယ်။

Acceptance criteria: Balanced journal ကို post လုပ်နိုင်ပြီး unbalanced journal, wrong currency, duplicate source posting တွေကို reject လုပ်နိုင်ရမယ်။

## Phase 06 — Wallet dashboard & balance UI

ဒီ Phase က Frontend implementation ကို တကယ်စတင်ပြီး customer-facing pages တွေ တည်ဆောက်မယ့် Phase ဖြစ်တယ်။

### Backend tasks

* Wallet list API

* Wallet balance API

* Currency metadata API

* Wallet status and permissions

* Consistent balance representation

### Frontend pages

* Customer dashboard

* Wallet list

* Wallet details

* Currency selector

* Available, posted and reserved balance display

* Recent transaction preview

* Empty-wallet and frozen-wallet states

### UI rules

Balance display မှာ အောက်ပါကွဲပြားချက်တွေကို ရှင်းရှင်းလင်းလင်း ဖော်ပြမယ်။

* Posted balance: Ledger ထဲက posted transactions တွေအပေါ် အခြေခံထားတဲ့ balance

* Reserved balance: Pending withdrawal/hold အတွက် ခွဲထားတဲ့ amount

* Available balance: `posted_balance - reserved_balance`

Customer တစ်ယောက်မှာ currency အများကြီးရှိနိုင်တာကြောင့် MMK, USD စတဲ့ balances တွေကို စုစုပေါင်း amount တစ်ခုတည်းအဖြစ် currency conversion မရှိဘဲ မပြရ။

### Tests

* Wallet owner only access

* Currency display and rounding

* Frozen/closed wallet behavior

* Loading, empty and error states

* Responsive dashboard

* Balance refresh and stale-data behavior

Acceptance criteria: UI မှာ ပြထားတဲ့ balance က Backend ရဲ့ authoritative response နဲ့ ကိုက်ညီရမယ်။ Frontend က balance ကို ကိုယ်တိုင်ဆုံးဖြတ်ပြီး financial transaction ခွင့်ပြု/ငြင်းပယ်တာ မလုပ်ရ။

## Phase 07 — Internal wallet transfer

### Backend flow

```
Validate request + idempotency
            ↓
Begin database transaction
            ↓
Lock wallet rows in stable order
            ↓
Check ownership / status / currency / balance
            ↓
Create transfer + balanced journal
            ↓
Update cached balances + outbox
            ↓
Commit
            ↓
Publish notifications asynchronously (in-app record + Reverb broadcast)
```

### Frontend flow

1. Source wallet ရွေးမယ်။

2. Destination wallet/account ရွေးမယ်။

3. Amount နဲ့ optional transfer reference ထည့်မယ်။

4. Backend က available balance, fees, destination eligibility ကို validate လုပ်မယ်။

5. Confirmation screen မှာ destination, amount, fee, total debit, currency ပြမယ်။

6. User က confirm လုပ်မယ်။

7. Request ကို idempotency key နဲ့ submit လုပ်မယ်။

8. Response အပေါ်မူတည်ပြီး success, pending, failed သို့မဟုတ် unknown/reconciling status ပြမယ်။

9. Receipt/transaction detail ကို ပြမယ်။

### Business rules

* `amount_minor > 0`

* Source နဲ့ destination မတူရ။

* Currency mismatch ကို FX feature မရှိသေးသရွေ့ reject လုပ်ရမယ်။

* `available_balance >= amount + fee` ဖြစ်ရမယ်။

* Concurrent requests တွေကြား overspending မဖြစ်ရ။

* Double-click သို့မဟုတ် retry ကြောင့် duplicate transfer မဖြစ်ရ။

* Client timeout ဖြစ်တာနဲ့ transfer failed လို့ မယူဆရ။ Request status ကို ပြန်စစ်ရမယ်။

### Frontend state machine

`editing → validating → confirming → submitting → succeeded | failed | pending/reconciling`

Submit လုပ်ပြီးချိန်မှာ button disable လုပ်ရုံနဲ့ duplicate protection ပြီးပြီလို့ မယူဆရ။ Backend idempotency က အဓိကကာကွယ်မှုဖြစ်တယ်။

### Tests

* Unit tests for business rules

* Feature tests for transfer API

* Concurrent transfer tests

* Idempotency replay tests

* Frontend form and confirmation tests

* End-to-end transfer success/failure tests

Acceptance criteria: Source balance မလုံလောက်ရင် transfer မဖြစ်ရ၊ successful transfer တစ်ခုအတွက် debit/credit နှစ်ဖက်စလုံး တိကျရမယ်။

## Phase 08 — Deposits

### Backend

* Deposit intent/request

* Manual deposit approval workflow

* Payment-provider interface/adapters

* Webhook inbox and signature verification

* Provider reference and amount/currency verification

* Duplicate webhook protection

* Deposit state transitions

* Ledger posting and wallet balance update

### Frontend

* Deposit method selection

* Deposit amount form

* Payment instructions/checkout redirection

* Deposit reference and status page

* Manual proof upload if required

* Deposit history

* Pending, expired, rejected and succeeded states

### Business rules

* Admin approval နဲ့ actual payment confirmation ကို ခွဲခြားရမယ်။

* Provider webhook ကို verify မလုပ်ဘဲ customer balance မတိုးရ။

* Duplicate webhook ကို ledger posting ထပ်မလုပ်ဘဲ handle လုပ်ရမယ်။

* Client redirect success တစ်ခုတည်းကို payment success အဖြစ် မယုံရ။

* Provider timeout/unknown outcome ဖြစ်ရင် status inquiry/reconciliation ကို အသုံးပြုရမယ်။

### Tests

* Invalid signature

* Duplicate provider event ID

* Amount/currency mismatch

* Webhook retries

* Manual approval permissions

* Frontend status refresh and delayed completion

Acceptance criteria: Provider payment ကို verified ဖြစ်ပြီးမှ credit လုပ်ရမယ်။ Same provider event ကို ထပ်ပို့လည်း duplicate wallet credit မဖြစ်ရ။

## Phase 09 — Withdrawals

### Backend

* Withdrawal request and review

* Wallet holds

* Reserved balance synchronization

* Payout provider adapter

* Provider status inquiry

* Timeout recovery

* Hold consumption/release

* Ledger posting on confirmed payout

* Audit trail

### Frontend

* Withdrawal form

* Available balance display

* Fee and total deduction preview

* Payout destination management/display

* Confirmation and status tracking

* Pending review / processing / reconciling screens

* Withdrawal history

* Support/review information for unresolved payouts

### Business rules

* Request စတင်ချိန်မှာ amount ကို hold လုပ်မယ်။

* Hold create/update နဲ့ reserved balance update ကို transaction တစ်ခုတည်းထဲမှာ လုပ်ရမယ်။

* Timeout ဖြစ်လို့ payout မသေချာသေးရင် hold ကို ဆက်ထားမယ်။

* Payout မဖြစ်ကြောင်း အတည်ပြုပြီးမှ failed/cancelled အဖြစ်သတ်မှတ်ပြီး hold release လုပ်မယ်။

* Confirmed payout အတွက် ledger entry ကို တစ်ကြိမ်သာ post လုပ်မယ်။

### Withdrawal states

`pending → reserved → processing → succeeded`

အခြေအနေမသေချာရင် `reconciling` သို့ ဝင်နိုင်တယ်။ Confirmed failure ဖြစ်ပြီးမှ သတ်မှတ်ထားတဲ့ policy အတိုင်း hold release လုပ်မယ်။

Acceptance criteria: Double withdrawal မဖြစ်ရ၊ provider timeout ဖြစ်ချိန်မှာ customer ရဲ့ ငွေကို မတော်တဆ ထပ်သုံးခွင့်မပေးရ။


# Part E — Customer UI, Admin UI & platform reliability

## Phase 10 — Transaction history, statements & Admin portal

ဒီ Phase မှာ customer portal နဲ့ finance/admin portal နှစ်ခုလုံးကို functional အပြည့်အဝ ဖြစ်အောင် တည်ဆောက်မယ်။

### Customer portal

* Unified transaction history

* Transfer, deposit, withdrawal filters

* Currency, status, date-range filters

* Transaction detail and receipt

* Cursor pagination

* Statement export

* Reversal/refund status display

* Clear handling of pending/reconciling transactions

### Admin portal

* Customer search and detail

* Wallet management and freeze/unfreeze

* Deposit review and approval

* Withdrawal review and approval

* Transfer and journal search

* Provider reference lookup

* Reconciliation discrepancy review

* Audit timeline

* Permission management

* Controlled correction/reversal workflow

### UI/UX rules

* Financial amount, currency, reference, status နဲ့ timestamp တွေကို တစ်သမတ်တည်းပြမယ်။

* `succeeded`, `failed`, `pending`, `reconciling` တို့ကို UI မှာ သီးခြားဖော်ပြမယ်။

* Approval/rejection အတွက် confirmation dialog နဲ့ reason field သုံးမယ်။

* High-risk action များအတွက် reason, actor, timestamp, target resource ကို audit trail ထဲမှာ သိမ်းမယ်။

* Permission မရှိတဲ့ action ကို UI မှာ ဖျောက်နိုင်ပေမယ့် Backend ကလည်း reject လုပ်ရမယ်။

* Table filtering/sorting ကို allowlisted query parameters နဲ့ပဲ support လုပ်မယ်။

* Statement ထုတ်တဲ့အခါ opening balance, movements, closing balance, currency နဲ့ period ကို ရှင်းရှင်းလင်းလင်းပြမယ်။

### Tests

* Cross-customer authorization

* Admin role/permission matrix

* Pagination stability

* Filter/sort correctness

* Statement totals vs ledger

* Audit records for privileged actions

* Responsive tables and accessible dialogs

Acceptance criteria: Customer က ကိုယ့် data ပဲ မြင်နိုင်ရမယ်။ Admin က ခွင့်ပြုထားတဲ့ action တွေပဲ လုပ်နိုင်ရမယ်။ Statement total တွေက ledger-derived figures နဲ့ ကိုက်ညီရမယ်။

## Phase 11 — Events, outbox & PostgreSQL partitioning

### Backend tasks

* `outbox_messages`

* Outbox worker and retry policy

* Event consumers

* `payment_events`

* `audit_events`

* `webhook_deliveries`

* Monthly partition creation

* Partition monitoring and retention policy

* Event deduplication

### Frontend tasks

* Notification center (REST polling + Reverb/Echo WebSocket push)

* Unread/read state

* Transaction status refresh

* Retry and stale-data behavior

* Duplicate notifications မပြစေဖို့ event ID handling

* Realtime update ရှိရင် reconnect and resync behavior — private channel `App.Models.User.{id}`၊ event `notification.created`

### Partitioning strategy

| Table                | Initial strategy                    |
| -------------------- | ----------------------------------- |
| `payment_events`     | Monthly RANGE partition             |
| `audit_events`       | Monthly RANGE partition             |
| `webhook_deliveries` | Monthly RANGE partition             |
| `webhook_inbox`      | Unpartitioned initially             |
| `outbox_messages`    | Unpartitioned initially             |
| `journal_entries`    | Unpartitioned initially             |
| `ledger_postings`    | Benchmark မလုပ်မချင်း unpartitioned |

Partitioned table တွေမှာ partition key မပါဝင်တဲ့ global uniqueness constraints ကို မရနိုင်တဲ့ ကန့်သတ်ချက်တွေရှိတယ်။ Event ID နဲ့ provider event ID ကို global deduplication လုပ်ဖို့ strategy ကို partitioning နဲ့ သီးခြား design လုပ်ရမယ်။

### Frontend realtime rules

Realtime event ရောက်လာတာကို authoritative balance အဖြစ် တိုက်ရိုက်မယူဆရ။ Event က status refresh လုပ်ဖို့ signal ဖြစ်နိုင်ပြီး၊ current financial state ကို Backend API က ပြန်လည်စစ်ဆေးရမယ်။

Laravel Reverb ကို Laravel Echo (`laravel-echo` + `pusher-js`) နဲ့ချိတ်ပြီး private channel `App.Models.User.{id}` ပေါ်မှာ `notification.created` event ကို ပို့မယ်။ Channel authorization ကို Sanctum bearer token နဲ့ `/broadcasting/auth` မှတစ်ဆင့် လုပ်မယ်။ WebSocket က signal အဖြစ်သာသုံးပြီး REST ကသာ authoritative ဖြစ်မယ်၊ ပြတ်သွား/reconnect ဖြစ်လျှင် polling နဲ့ resync ကို ဆက်လုပ်မယ်။ `BROADCAST_CONNECTION` သို့မဟုတ် `VITE_REVERB_APP_KEY` မရှိလျှင် realtime ပိတ်ပြီး polling သာ အလုပ်လုပ်ရမယ်။

Acceptance criteria: Worker restart, duplicate event, failed delivery နဲ့ partition rollover ဖြစ်လည်း financial transaction data မပျောက်ရ။ UI က stale data ကို လိုအပ်သလို refresh လုပ်နိုင်ရမယ်။

## Phase 12 — Reconciliation & recovery

### Backend reconciliation

* Wallet balance vs ledger

* Reserved balance vs active holds

* Provider transaction vs local deposit/withdrawal

* Provider settlement vs clearing accounts

* Bank settlement vs accounting records

* Missing/duplicate/mismatched transactions

* Reconciliation runs, items and resolution records

### Frontend reconciliation UI

* Reconciliation run list

* Run details and totals

* Mismatch filters

* Local vs provider amount comparison

* Missing transaction indicators

* Review/assign/resolution workflow

* Resolution reason and audit timeline

### Business rule

Discrepancy တွေ့တာနဲ့ wallet balance ကို အလိုအလျောက်ပြင်မပစ်ရ။ အကြောင်းရင်းကို ရှာဖွေပြီး authorized correction/reversal workflow နဲ့ပဲ ဖြေရှင်းရမယ်။

### Recovery states

`detected → investigating → resolution_pending → resolved`

လိုအပ်တဲ့ approval policy နဲ့ actor separation ကို သတ်မှတ်ထားရမယ်။

Acceptance criteria: Inject လုပ်ထားတဲ့ mismatch တွေကို detect လုပ်နိုင်ရမယ်။ Discrepancy ကို ဘယ်သူ၊ ဘယ်အချိန်၊ ဘာကြောင့် resolve လုပ်ခဲ့တယ်ဆိုတာ ပြန်လည်စစ်ဆေးနိုင်ရမယ်။

## Phase 13 — Security, accessibility, QA & performance

ဒီ Phase ကို နောက်ဆုံးမှ စစ်မယ့်အစား Phase တိုင်းမှာ quality gates ထားပြီး ဒီ Phase မှာ end-to-end audit လုပ်မယ်။

### Backend quality gates

* Authentication and authorization tests

* Idempotency and replay tests

* Concurrent transfer/withdrawal tests

* Ledger balance invariants

* Database constraint verification

* Provider webhook security

* Queue retry and recovery tests

* Query performance and partition pruning

* Secrets and dependency scanning

### Frontend quality gates

* TypeScript strict typecheck

* ESLint and formatter

* Unit/component tests

* API integration tests

* End-to-end customer journeys

* Keyboard navigation and focus tests

* Accessibility checks against applicable WCAG 2.2 criteria

* Responsive layout testing

* Dark/light theme contrast checks if both themes are supported

* UI states for timeout, pending, error and retry

### Performance tests

| Scenario                          | Objective                                         |
| --------------------------------- | ------------------------------------------------- |
| Concurrent requests to one wallet | Overspending မဖြစ်စေရန်                           |
| Duplicate idempotency requests    | Duplicate money movement မဖြစ်စေရန်               |
| Large transaction history         | Query latency and pagination                      |
| High event volume                 | Partition pruning and index efficiency            |
| Outbox backlog                    | Worker throughput and recovery                    |
| Reconciliation at scale           | Runtime, locks and database load                  |
| Frontend dashboard                | Render speed, network requests and loading states |

### Non-functional requirements

Project requirement တွေမှာ အောက်ပါအချက်တွေကို measurable target အဖြစ် သတ်မှတ်မယ်။

* Availability and recovery objectives

* API latency percentiles

* Throughput under agreed workloads

* Error rate

* Maximum queue backlog/recovery time

* Backup retention and restore time

* Accessibility conformance target

* Security risk acceptance policy

Target တန်ဖိုးတွေကို workload, infrastructure နဲ့ business requirement မသိဘဲ အတိအကျမခန့်မှန်းဘူး။ Requirement review မှာ သတ်မှတ်ပြီး benchmark evidence နဲ့ verify လုပ်မယ်။

Acceptance criteria: Release အတွက် critical financial correctness, authorization, security, accessibility နဲ့ performance gates တွေ pass ဖြစ်ရမယ်။ Unresolved high-risk defects ရှိရင် release ကို block လုပ်မယ်။

## Phase 14 — Deployment & operations

### Backend / infrastructure

* Development, staging, production environments

* TLS and secrets management

* Least-privilege database roles

* Database migration/recovery plan

* Redis queue workers and supervision

* Scheduled tasks and partition automation

* Structured logs and correlation IDs

* Metrics, dashboards and alerts

* Backup/restore drills

* Incident response runbooks

### Frontend deployment

* Production Vite build

* Environment-specific API base URLs

* Cache policy and asset versioning

* CSP/security headers

* SPA route fallback configuration

* Source map policy

* Error reporting and privacy-safe telemetry

* Production smoke tests

* Staged rollout and rollback procedure

### Critical alerts

* Failed payout / unknown payout outcome

* Reconciliation discrepancy

* Webhook processing backlog

* Outbox queue backlog

* Partition missing

* Database connection saturation

* Elevated error rate/latency

* Backup failure

Acceptance criteria: Backup ကို restore စမ်းသပ်ပြီးပြီ၊ alerts တွေကို test လုပ်ပြီးပြီ၊ staging မှာ end-to-end recovery workflow တွေ အောင်မြင်ပြီးပြီ။


# Part F — Mandatory code design, business logic & UI guidelines

အထက်ပါ Phase တွေက ဘာတွေတည်ဆောက်မလဲဆိုတာ သတ်မှတ်ပေးတယ်။ ဒီအပိုင်းကတော့ developer သို့မဟုတ် AI coding agent က ဘယ် Phase မှာပဲ code ရေးရေး လိုက်နာရမယ့် project-wide engineering rules တွေဖြစ်တယ်။

## 1. Backend code design guidelines

### Layer responsibilities

HTTP Controller

HTTP request/response only

FormRequest + Authorization

Input validation and access checks

DTO / Command

Typed and explicit input

Application Service

Use-case orchestration and transaction boundary

Domain Services

Financial invariants and business rules

Repository / Persistence

Data access and query implementation

PostgreSQL

Constraints, atomicity and durable records

### Mandatory rules

* Single responsibility: Class တစ်ခုစီမှာ တာဝန်တစ်ခုကို ရှင်းလင်းစွာ သတ်မှတ်မယ်။

* Dependency inversion: Core business rules က infrastructure implementation ကို တိုက်ရိုက်မမှီခိုစေရ။

* Explicit transactions: Financial write operation တွေမှာ transaction boundary ကို ရှင်းရှင်းလင်းလင်း သတ်မှတ်မယ်။

* Typed contracts: DTOs, interfaces, enums နဲ့ return types ကို လိုအပ်သလို သုံးမယ်။

* Centralized exceptions: Domain errors နဲ့ HTTP errors ကို ခွဲခြားပြီး consistent response ထုတ်မယ်။

* No hidden side effects: Model observer/event callback တွေက money movement ကို မသိမသာ မလုပ်စေရ။

* No business logic in UI: Frontend က ပြသမှုနဲ့ interaction ကို ကိုင်တွယ်ပြီး financial decision ကို Backend က ဆုံးဖြတ်မယ်။

* Testability: Service တစ်ခုစီမှာ dependencies ကို အစားထိုးစမ်းသပ်နိုင်ရမယ်။

## 2. Financial business logic guidelines

ဒီအချက်တွေကို code review မှာ မဖြစ်မနေစစ်မယ်။

| Rule               | Required behavior                                                    |
| ------------------ | -------------------------------------------------------------------- |
| Atomicity          | Financial records, ledger, balance and outbox are committed together |
| Double entry       | Journal debit = credit                                               |
| Idempotency        | Same request replay must not move money twice                        |
| Concurrency        | Locking/consistency strategy prevents overspending                   |
| Currency integrity | Amounts and ledger accounts use the correct currency                 |
| Immutability       | Posted entries are not directly edited or deleted                    |
| Reversals          | Corrections use controlled compensating entries                      |
| Authorization      | Ownership and permission checked on every protected request          |
| External payments  | Provider outcomes are verified, not inferred from redirects          |
| Reconciliation     | Mismatches are detected and reviewed, not silently overwritten       |

### Financial logic နဲ့ UI logic ကို မရောထွေးရ

ဥပမာ Transfer confirmation dialog မှာ “Available balance လုံလောက်တယ်” လို့ ပြထားပေမယ့် request submit လုပ်ချိန်မှာ တခြား transaction တစ်ခုက balance ကို သုံးသွားနိုင်တယ်။

ဒါကြောင့် Frontend validation က user experience အတွက်ဖြစ်ပြီး Backend က transaction အတွင်း balance ကို ပြန်စစ်ရမယ်။

## 3. Frontend code design guidelines

### Component responsibility

| Layer               | တာဝန်                                                    |
| ------------------- | -------------------------------------------------------- |
| Page component      | Page composition and route-level state                   |
| Feature component   | Transfer form, wallet card, review panel စတဲ့ feature UI |
| Shared UI component | Reusable primitive and visual behavior                   |
| API service         | HTTP requests and response parsing                       |
| Query/state layer   | Server data fetching, caching, invalidation              |
| Pinia store         | Cross-page/shared client state                           |
| Composable          | Reusable UI behavior and interaction logic               |
| Formatter/utility   | Currency/date/number presentation                        |

### Mandatory rules

* Vue components တွေကို အသေးစိတ် တာဝန်ခွဲမယ်။ Giant component မဖြစ်စေရ။

* API request တွေကို page/component တစ်ခုချင်းစီမှာ ထပ်ခါထပ်ခါ မရေးရ။

* API types တွေကို OpenAPI မှ generate လုပ်နိုင်ရင် manual duplicate definitions ကို လျှော့မယ်။

* Pinia ထဲမှာ server data အားလုံးကို မလိုအပ်ဘဲ ထပ်သိမ်းထားတာမျိုး မလုပ်ရ။

* API errors ကို component တစ်ခုချင်းစီမှာ မတူညီစွာ parse မလုပ်ဘဲ shared error handling သုံးမယ်။

* Route guards ကို access control အဖြစ် မယူဆရ။

* Money calculations အတွက် floating-point arithmetic မသုံးရ။

* Loading, empty, error, disabled, pending နဲ့ success states မပါဘဲ production page တစ်ခုကို complete လို့ မသတ်မှတ်ရ။

## 4. UI design guidelines

### Design system governance

* Shared design tokens ကိုသာ သုံးမယ်။

* Color, spacing, typography နဲ့ border radius တွေကို arbitrary values အဖြစ် မဖြန့်ကျက်စေရ။

* Shared component တစ်ခုကို ပြင်တဲ့အခါ အသုံးပြုနေတဲ့ feature တွေကို regression test လုပ်မယ်။

* Component တစ်ခုချင်းစီအတွက် props, events, states, accessibility requirements နဲ့ usage examples ကို documentation ထဲမှာ ထည့်မယ်။

* Visual consistency အတွက် page တစ်ခုချင်းစီက ကိုယ်ပိုင် button/modal/table pattern မဖန်တီးရ။

* Financial confirmation screens မှာ amount, currency, destination နဲ့ fees ကိုရှင်းလင်းစွာ ပြရမယ်။

* Error message တွေက ဘာဖြစ်တယ်ဆိုတာတင်မက ဘယ်လိုဆက်လုပ်ရမယ်ဆိုတာပါ ဖော်ပြရမယ်။

### UI component Definition of Done

Component တစ်ခုကို complete လို့သတ်မှတ်ဖို့ —

* Design tokens ကို အသုံးပြုထားခြင်း

* TypeScript props/events typed ဖြစ်ခြင်း

* Keyboard accessibility ရှိခြင်း

* Loading/disabled/error states သတ်မှတ်ထားခြင်း

* Responsive behavior စစ်ဆေးထားခြင်း

* Unit/component tests ပါဝင်ခြင်း

* Usage documentation ပါဝင်ခြင်း

# Part G — Documentation, traceability & quality evidence

ISO-aligned engineering လုပ်ချင်ရင် code တင်မကဘဲ requirement ကနေ release အထိ traceability ရှိဖို့လိုတယ်။

အကြံပြုထားတဲ့ documentation structure —

```
docs/
├── requirements/
├── architecture/
│   ├── adr/
│   ├── diagrams/
│   └── api/
├── business/
│   ├── accounting-rules.md
│   ├── state-machines.md
│   └── permissions-matrix.md
├── backend/
│   ├── coding-guidelines.md
│   ├── testing-strategy.md
│   └── database-guidelines.md
├── frontend/
│   ├── architecture.md
│   ├── component-guidelines.md
│   ├── design-system.md
│   └── accessibility-checklist.md
├── security/
├── testing/
├── operations/
├── compliance/
│   ├── standards-mapping.md
│   ├── traceability-matrix.md
│   └── review-evidence/
└── runbooks/
```

### Requirement traceability example

| Requirement ID | Implementation                    | Verification                      |
| -------------- | --------------------------------- | --------------------------------- |
| `FIN-001`      | `LedgerPostingService`            | Balanced/unbalanced journal tests |
| `FIN-002`      | Idempotency service               | Duplicate request tests           |
| `SEC-001`      | Policies and authorization        | Access-control tests              |
| `UI-001`       | Shared design system              | Component and visual review       |
| `A11Y-001`     | Accessible form/dialog components | Keyboard and accessibility tests  |
| `OPS-001`      | Partition manager                 | Partition automation test         |

ဒီ matrix ကို actual requirements နဲ့ code paths အပေါ် အခြေခံပြီး တိကျအောင် update လုပ်ရမယ်။ ဥပမာ `FIN-001` က ဘယ် test file မှာ verify လုပ်ထားတယ်ဆိုတာပါ link ချိတ်ထားနိုင်ရမယ်။

# Part H — AI coding agent implementation protocol

Copilot, Claude Code သို့မဟုတ် local coding agent တစ်ခုကို သုံးမယ်ဆိုရင် အောက်ပါ workflow ကို Phase တိုင်းမှာ မဖြစ်မနေလိုက်နာခိုင်းပါ။

#### 1

Inspect

Existing files, conventions, dependencies, current test status

#### 2

Specify

Requirements, acceptance criteria, business invariants, API contract

#### 3

Design

Minimal design, affected modules, schema and failure cases

#### 4

Implement

Smallest coherent change; avoid unrelated refactoring

#### 5

Verify

Run relevant tests, lint, typecheck, security and accessibility checks

#### 6

Review

Review financial correctness, authorization, concurrency and UX states

#### 7

Document

Update API, component, architecture and traceability documents

#### 8

Report

Files changed, commands run, results, known gaps and next step

### Ready-to-copy master instruction for the coding agent

Digital Wallet Platform — Engineering Standards & Implementation Protocol

# Digital Wallet Platform — Engineering Standards & Implementation Protocol

You are implementing a production-oriented Digital Wallet & Financial Ledger Platform using Laravel, PHP, PostgreSQL, Redis, Vue 3, TypeScript, Pinia, Vue Router, Vite, and Tailwind CSS.

## 1. Engineering principles

Follow a documented, requirements-driven software lifecycle aligned with the applicable principles of ISO/IEC/IEEE 12207, ISO/IEC/IEEE 29148, ISO/IEC 25010, ISO/IEC 27001, ISO 9241-210, and ISO/IEC 40500/WCAG 2.2.

Do not claim ISO certification or full compliance unless independently assessed and supported by evidence. Maintain traceability from requirements to implementation, tests, and release evidence.

## 2. Mandatory workflow

For every task:

1. Inspect the existing repository, architecture, conventions, dependencies, and tests before editing.

2. Identify the relevant requirement IDs, business rules, acceptance criteria, and affected modules.

3. Explain the smallest viable implementation plan before making changes.

4. Implement only the requested scope. Do not perform unrelated refactoring.

5. Add or update automated tests for the behavior and relevant failure cases.

6. Run the applicable tests, linting, static analysis, TypeScript typecheck, and build commands.

7. Review security, authorization, financial correctness, concurrency, accessibility, responsive behavior, and error states.

8. Update the relevant documentation and requirements traceability.

9. Report changed files, commands executed, actual results, unresolved issues, and assumptions.

Never claim that a test passed unless it was actually executed and passed. Never invent test results, files, API behavior, or compliance evidence.

## 3. Backend rules

* Use clear module boundaries and dependency injection.

* Separate HTTP concerns, validation, DTOs, application services, domain rules, and persistence.

* Keep financial invariants inside trusted backend/domain boundaries.

* Use explicit database transaction boundaries for money movement.

* Implement idempotency, concurrency control, and authorization.

* Use integer minor units for money and enforce currency consistency.

* Enforce double-entry accounting and immutable posted ledger records.

* Use reversal/correction entries rather than editing posted financial records.

* Treat external provider callbacks as untrusted until verified.

* Handle timeouts and unknown outcomes explicitly.

* Do not publish notifications or external events before the financial transaction commits.

* Use a transactional outbox where reliable event delivery is required.

* Use database constraints and least-privilege access in addition to application validation.

* Do not partition ledger tables without documented benchmark evidence and a reviewed integrity design.

## 4. Frontend rules

* Use Vue 3 Composition API and TypeScript strict mode.

* Keep page composition, feature logic, reusable UI, API access, and shared state separated.

* Use shared UI primitives and documented design tokens.

* Do not duplicate API contracts manually when generated types are available.

* Do not treat frontend validation or route guards as security controls.

* Do not use floating-point arithmetic for financial calculations.

* Handle loading, empty, error, pending, timeout, success, and retry states.

* Ensure forms, tables, dialogs, navigation, focus management, and status messages are accessible.

* Test responsive layouts and supported themes.

* Do not introduce new UI primitives without reviewing the existing component library first.

## 5. Business logic and financial safety

Before implementing a money-moving use case, specify:

* Preconditions and authorization

* State transitions

* Ledger postings

* Transaction boundaries

* Idempotency scope

* Locking and concurrency strategy

* External side effects

* Failure, retry, timeout, and reconciliation behavior

* Audit requirements

* Unit, feature, concurrency, and end-to-end tests

Never silently fix reconciliation mismatches or mark uncertain external payment outcomes as failed without verification.

## 6. Definition of Done

A task is complete only when:

* Acceptance criteria are met.

* Relevant automated tests pass.

* Typecheck, lint, and build pass where applicable.

* Security and authorization have been reviewed.

* Financial invariants are tested for financial features.

* UI states and accessibility have been reviewed for frontend features.

* Documentation and traceability are updated.

* Remaining risks and limitations are reported.

If a requirement is ambiguous or a financial rule is missing, identify the ambiguity and request clarification instead of inventing a business rule.

# Final implementation order

ဒီ project ကို တကယ်တည်ဆောက်တဲ့အခါ အောက်ပါ order ကို လိုက်နာဖို့ အကြံပြုတယ်။

1. Phase 00–01: Requirements, business rules, architecture, API contract

2. Phase 02–03: Backend/Frontend foundations နဲ့ UI design system

3. Phase 04: Authentication/RBAC — Customer and Admin portals

4. Phase 05: Database and Ledger Core

5. Phase 06: Wallet dashboard and balance UI

6. Phase 07: Internal transfers — Backend + Frontend + tests

7. Phase 08: Deposits — Backend + Frontend + provider verification

8. Phase 09: Withdrawals — Backend + Frontend + holds/recovery

9. Phase 10: History, statements and Admin portal

10. Phase 11–12: Outbox, partitioning, reconciliation and recovery

11. Phase 13: Full security, accessibility, performance and regression testing

12. Phase 14: Staging, production deployment and operational readiness

အဓိကဆုံးက Feature တစ်ခုကို Backend-only ပြီးမှ Frontend-only လုပ်တာမဟုတ်ဘဲ vertical slice နဲ့ တည်ဆောက်ဖို့ ဖြစ်တယ်။ ဥပမာ Transfer feature ဆိုရင် database, business service, API contract, UI, error states, tests နဲ့ audit requirements အားလုံးကို feature တစ်ခုတည်းအဖြစ် ပြီးစီးအောင်လုပ်ပြီးမှ နောက် feature ကို ဆက်သွားမယ်။

ဒီနည်းလမ်းက financial correctness, code maintainability, consistent UI/UX နဲ့ standards-aligned verification တို့ကို project အစကနေ အဆုံးအထိ ထိန်းသိမ်းနိုင်စေမယ်။
