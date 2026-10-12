# Lumina — Documentation Index

Authoritative source: `BLUEPRINT.md` (requirement IDs, phases 00–14, quality gates).

## Requirements & compliance

| Doc | Purpose |
| --- | ------- |
| `requirements/business-requirements.md` | BR-001…BR-018 |
| `requirements/functional-requirements.md` | FR-101…FR-803 |
| `requirements/non-functional-requirements.md` | NFR-101…NFR-503 (quality, ISO alignment) |
| `requirements/acceptance-criteria.md` | AC-101…AC-504 |
| `compliance/standards-mapping.md` | ISO/reference standard application + evidence |
| `compliance/requirements-traceability.md` | Requirement → implementation → verification |
| `compliance/risk-register.md` | Live risk register (financial/security) |
| `compliance/review-evidence/` | Signed artefact results |

## Business rules

| Doc | Purpose |
| --- | ------- |
| `business/business-flow.md` | End-to-end business flows (wallets, deposits, transfers, withdrawals, ledger, reconciliation) |
| `business/business-rules.md` | Operational rules a day-in-the-life must satisfy |
| `business/accounting-rules.md` | Ledger, posting, anchoring, rounding |
| `business/transaction-state-machines.md` | Transfer/deposit/withdrawal state tables |
| `business/permissions-matrix.md` | Role × permission × endpoint matrix |

## Architecture

| Doc | Purpose |
| --- | ------- |
| `architecture/overview.md` | System diagrams, URLs, error envelope |
| `architecture/api/error-codes.md` | Stable machine-readable error codes |
| `architecture/api/openapi.yaml` | OpenAPI 3.1 contract (44 paths, validated by CI) |
| `architecture/adr/ADR-000*.md` | Decision records (money, modules, locking, outbox, partitioning, reversal) |

## Product areas

| Doc | Purpose |
| --- | ------- |
| `backend/architecture.md` | Module map + backend invariants |
| `frontend/architecture.md` | Target-state frontend design (frontend track) |
| `frontend/design-system.md` | Design tokens, component inventory, UI conventions |
| `frontend/accessibility-checklist.md` | WCAG 2.2 AA checklist and verification |
| `security/security-requirements.md` | Security controls + test mapping |
| `testing/testing-strategy.md` | Test tiers, money-movement rules, evidence |
| `operations/deployment.md` | Environments, deploy/rollback, backups |
| `operations/monitoring.md` | Alerts and their runbook links |
| `runbooks/*` | Incident procedures |

## Repository contribution

`AGENTS.md` defines implementation conventions (incl. `.ai/rules`), verification and
the Pint/`php artisan test` gates that must pass on every merge.