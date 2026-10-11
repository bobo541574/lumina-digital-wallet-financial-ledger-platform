# Permissions Matrix

Digital Wallet & Financial Ledger Platform (Lumina)

Status: Baselined for MVP
Source of truth: `App\Modules\Identity\Enums\Permission::forRole()` and
`database/migrations/...platform-permissions.php` seeders. This document must be kept
in sync with the code.

---

## 1. Roles

| Role             | Purpose                                                        |
| ---------------- | -------------------------------------------------------------- |
| `customer`       | Owns wallets; initiates transfers, deposits, withdrawals.      |
| `finance_operator` | Approves/confirms deposits, processes withdrawals, reconciles. |
| `auditor`        | Read-only: journals, audit events, reconciliation.             |
| `admin`          | Everything (union of all permissions); manages users/wallets.  |

Effective permissions = union of the grants of all roles assigned to a user.

---

## 2. Permission catalog

| Permission              | Group            | Meaning                                    |
| ----------------------- | ---------------- | ------------------------------------------ |
| `wallets.view`          | Wallet           | View own wallets / balances                |
| `wallets.manage`        | Wallet           | Admin: list, view, freeze, unfreeze wallets |
| `transfers.create`      | Transfer         | Initiate an internal transfer              |
| `transfers.view`        | Transfer         | View transfers (own, or filter for staff)  |
| `deposits.create`       | Deposit          | Create manual/provider deposit             |
| `deposits.view`         | Deposit          | View own (or all, if granted) deposits     |
| `deposits.approve`      | Deposit          | Approve/reject operator-reviewed deposits  |
| `deposits.confirm`      | Deposit          | Confirm funds received; settles deposit    |
| `withdrawals.create`    | Withdrawal       | Request a withdrawal                       |
| `withdrawals.view`      | Withdrawal       | View own (or all, if granted) withdrawals  |
| `withdrawals.approve`   | Withdrawal       | Review/approve withdrawal list             |
| `withdrawals.process`   | Withdrawal       | Initiate payout to the provider            |
| `ledger.view`           | Ledger           | View journals and postings                 |
| `ledger.correct`        | Ledger           | Perform controlled journal reversal        |
| `audit.view`            | Audit            | Query audit events                         |
| `reconciliation.view`   | Reconciliation   | View runs and mismatch items               |
| `reconciliation.run`    | Reconciliation   | Start runs and resolve/ignore items        |
| `users.manage`          | Administration   | List/search/manage users and roles         |
| `roles.manage`          | Administration   | Manage roles and role grants               |

---

## 3. Role → permission grant matrix

| Permission               | customer | finance_operator | auditor | admin |
| ------------------------ | :------: | :--------------: | :-----: | :---: |
| `wallets.view`           | ✓        | ✓                | ✓       | ✓     |
| `wallets.manage`         | —        | —                | —       | ✓     |
| `transfers.create`       | ✓        | —                | —       | ✓     |
| `transfers.view`         | ✓        | ✓                | ✓       | ✓     |
| `deposits.create`        | ✓        | —                | —       | ✓     |
| `deposits.view`          | ✓        | ✓                | ✓       | ✓     |
| `deposits.approve`       | —        | ✓                | —       | ✓     |
| `deposits.confirm`       | —        | ✓                | —       | ✓     |
| `withdrawals.create`     | ✓        | —                | —       | ✓     |
| `withdrawals.view`       | ✓        | ✓                | ✓       | ✓     |
| `withdrawals.approve`    | —        | ✓                | —       | ✓     |
| `withdrawals.process`    | —        | ✓                | —       | ✓     |
| `ledger.view`            | —        | ✓                | ✓       | ✓     |
| `ledger.correct`         | —        | ✓                | —       | ✓     |
| `audit.view`             | —        | —                | ✓       | ✓     |
| `reconciliation.view`    | —        | ✓                | ✓       | ✓     |
| `reconciliation.run`     | —        | ✓                | —       | ✓     |
| `users.manage`           | —        | —                | —       | ✓     |
| `roles.manage`           | —        | —                | —       | ✓     |

Legend: ✓ granted · — not granted. `admin` receives every permission.

---

## 4. Endpoint → permission mapping

| Method & path                                          | Permission             | Ownership rule                                   |
| ------------------------------------------------------ | ---------------------- | ------------------------------------------------ |
| `GET  /api/v1/wallets`                                 | `wallets.view`         | own only                                         |
| `POST /api/v1/wallets`                                 | authenticated         | own                                              |
| `GET  /api/v1/wallets/{wallet}/balance`, `/transactions`, `/statement(/export)` | `wallets.view` | own via policy (owner or `wallets.manage`)       |
| `POST /api/v1/transfers`                               | `transfers.create`     | both wallets owned (or permitted)                |
| `GET  /api/v1/transfers/{transfer}`                    | `transfers.view`       | owner of either wallet, else `ledger.view`       |
| `POST /api/v1/deposits` / `deposits/provider`          | `deposits.create`      | own wallet                                       |
| `GET  /api/v1/deposits` / `{deposit}`                  | `deposits.view`        | own, or `deposits.approve`/`confirm`/`reconciliation.view` |
| `POST /api/v1/deposits/{deposit}/approve` / `reject`   | `deposits.approve`     | segregation of duty (no self-approval)           |
| `POST /api/v1/deposits/{deposit}/confirm`              | `deposits.confirm`     | —                                                |
| `POST /api/v1/withdrawals`                             | `withdrawals.create`   | own wallet                                       |
| `GET  /api/v1/withdrawals` / `{withdrawal}`            | `withdrawals.view`     | own, or `withdrawals.approve`/`process`/`audit.view` |
| `POST /api/v1/withdrawals/{withdrawal}/initiate`       | `withdrawals.process`  | —                                                |
| `POST /api/v1/withdrawals/{withdrawal}/cancel`         | owner (`@cancel`)      | own only                                         |
| `GET  /api/v1/admin/users`                             | `users.manage`         | —                                                |
| `GET  /api/v1/admin/transfers`                         | `ledger.view`          | —                                                |
| `GET  /api/v1/admin/journal-entries`                   | `ledger.view`          | —                                                |
| `POST /api/v1/admin/journal-entries/{entry}/reverse`   | `ledger.correct`       | —                                                |
| `GET  /api/v1/admin/deposits`                          | `deposits.approve`     | —                                                |
| `GET  /api/v1/admin/withdrawals`                       | `withdrawals.approve`  | —                                                |
| `GET  /api/v1/admin/provider-transactions`             | `reconciliation.view`  | —                                                |
| `GET  /api/v1/admin/reconciliation/runs(/{run})/items` | `reconciliation.view`  | —                                                |
| `POST /api/v1/admin/reconciliation/run`                | `reconciliation.run`   | —                                                |
| `POST /api/v1/admin/reconciliation/items/{item}/resolve`| `reconciliation.run`  | —                                                |
| `GET  /api/v1/admin/audit-events`                      | `audit.view`           | —                                                |
| `GET  /api/v1/admin/wallets(/{wallet})` / `freeze` / `unfreeze` | `wallets.manage` | —                                          |
| `GET  /api/v1/admin/users/{user}`                      | `users.manage`         | —                                                |
| `PUT  /api/v1/admin/users/{user}/roles`                | `users.manage`         | last admin cannot lose the `admin` role          |
| `PUT  /api/v1/admin/users/{user}/permissions`          | `users.manage`         | direct (non-role) grants                         |
| `GET  /api/v1/admin/roles(/{role})`                    | `roles.manage`         | —                                                |
| `PUT  /api/v1/admin/roles/{role}/permissions`          | `roles.manage`         | `admin` must keep `roles.manage`                 |
| `GET  /api/v1/admin/permissions`                       | `roles.manage`         | permission catalog                               |
| `GET  /api/v1/notifications` / `unread-count`          | authenticated          | own only                                         |
| `POST /api/v1/notifications/read-all`                  | authenticated          | own only                                         |
| `POST /api/v1/notifications/{notification}/read`       | authenticated          | own only (policy)                                |

All admin/finance endpoints additionally require `auth:sanctum`,
`throttle:admin` and the `RequireTwoFactor` middleware when MFA is enabled.

---

## 5. Cross-cutting authorization rules

- Mutating customer endpoints require the caller to own the target resource; using
  another customer's id yields `404 RESOURCE_NOT_FOUND` (masking), never `200`.
- A finance operator cannot approve their own deposit (`DEPOSIT_SELF_APPROVAL`).
- Nobody (`admin` included) may update or delete posted journals/postings; corrections
  use `ledger.correct` reversals.
- The matrix is enforced in controllers/policies, not by hiding UI; the UI renders
  actions conditionally but never acts as an authorization boundary.