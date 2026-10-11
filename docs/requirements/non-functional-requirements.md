# Non-Functional Requirements

Digital Wallet & Financial Ledger Platform (Lumina)

Status: Baselined for MVP (targets to be confirmed by requirement review + benchmark
evidence)
Owner: Platform / Reliability
Companion: `docs/requirements/business-requirements.md`, `docs/operations/deployment.md`

Measurable targets are set during requirements review with workload and
infrastructure data; they are verified by benchmark evidence, never assumed. This
document records the current agreed set and the evidence location for each.

---

## 1. Security (NFR-100 series)

| ID      | Requirement                                                                                | Target / evidence                                   |
| ------- | ------------------------------------------------------------------------------------------ | --------------------------------------------------- |
| NFR-101 | Secrets are never committed or logged; sensitive values are redacted in logs/audit.        | `config/security.php` redact_keys; Redactor edit     |
| NFR-102 | Ownership and permission checks on every protected request; foreign ids never expose data. | Wallet/Admin feature tests                          |
| NFR-103 | Webhook callbacks are signature-verified before state change.                              | `SignatureTest`                                     |
| NFR-104 | Rate limiting on auth, webhook, money and admin endpoints.                                 | Named limiters in `config/security.php`             |
| NFR-105 | Posted financial records cannot be updated or deleted (app + DB triggers).                 | `LedgerPostingServiceTest`, `MigrationConventionsTest` |
| NFR-106 | Privileged roles require MFA when `SECURITY_ADMIN_MFA_REQUIRED=true`.                      | `TwoFactorTest`                                     |

## 2. Performance & reliability (NFR-200 series)

Targets are placeholders to be finalized in requirements review; verify with
`composer bench` (`tests/Performance/BenchmarkTest.php`) and record evidence in
`docs/compliance/review-evidence/`.

| ID      | Requirement                                                   | Target (proposed)                |
| ------- | ------------------------------------------------------------- | -------------------------------- |
| NFR-201 | API latency percentile (p95, authenticated reads/writes)      | < 500ms p95 on reference HW       |
| NFR-202 | Throughput under agreed workload                               | TBD in review                    |
| NFR-203 | Error rate                                                      | < 0.1% of requests               |
| NFR-204 | Concurrent transfers to one wallet never overspend             | Verified by concurrency test     |
| NFR-205 | Maximum outbox backlog / recovery time                         | TBD in review                    |
| NFR-206 | Query latency on large transaction history + partition pruning| Pruning verified; volume scale target TBD (see `review-evidence/partitioning-benchmark.md`) |
| NFR-207 | Availability objective                                         | 99.9% (proposed; confirm in infra review) |

## 3. Operations (NFR-300 series)

| ID      | Requirement                                                    | Target / evidence                  |
| ------- | -------------------------------------------------------------- | ---------------------------------- |
| NFR-301 | Database backups automated, restored and tested quarterly.     | `docs/operations/deployment.md`    |
| NFR-302 | Structured logs with request correlation ids.                  | `X-Request-Id` + logging context   |
| NFR-303 | Critical alerts fire on payout failure, reconciliation discrepancy, webhook/outbox backlog, missing partition, DB saturation, elevated errors, backup failure. | `docs/operations/monitoring.md`    |
| NFR-304 | Incident response runbooks documented and rehearsed.           | `docs/runbooks/*`                  |

## 4. Accessibility & UX (NFR-400 series)

Toward ISO/IEC 40500:2025 (WCAG 2.2).

| ID      | Requirement                                                           | Target / evidence                              |
| ------- | --------------------------------------------------------------------- | ---------------------------------------------- |
| NFR-401 | Conformance target for the customer and admin portals.                | WCAG 2.2 Level AA (proposed; verified by a11y tests) |
| NFR-402 | Keyboard-only navigation, visible focus, labelled forms, accessible dialogs and status updates. | Frontend unit/e2e a11y tests (Phase 13) |
| NFR-403 | Responsive layouts and reflow; financial confirmations show amount, currency, destination, fees. | Frontend responsive tests (Phase 13) |

## 5. Compliance (NFR-500 series)

| ID      | Requirement                                                                     | Evidence |
| ------- | ------------------------------------------------------------------------------- | -------- |
| NFR-501 | Requirements → implementation → test traceability maintained per phase.         | `docs/compliance/requirements-traceability.md` |
| NFR-502 | Standards applied as project policies (ISO 12207/29148/25010/27001, ISO 9241-210, WCAG 2.2). | `docs/compliance/standards-mapping.md` |
| NFR-503 | No claim of ISO certification without independent audit.                        | `docs/compliance/README` note |

---

## 6. Target verification protocol

- Feature/unit tests prove functional correctness (CI, green suite).
- `composer bench` measures benchmark scenarios and records results under
  `docs/compliance/review-evidence/` (e.g.
  `docs/compliance/review-evidence/partitioning-benchmark.md`).
- Release gates (Part F Definition of Done) require the documented entry above; an
  unattested target does not block code but is explicitly reported as "not yet
  measured" in release notes.