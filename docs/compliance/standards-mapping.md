# Standards Mapping

Digital Wallet & Financial Ledger Platform (Lumina)

Status: Baselined
Purpose: Records how each referenced standard is applied in this project and where
its implementation evidence lives. This is a **statement of intent applied via
project policies and evidence**, not a claim of certification. Certification or full
conformance is only claimed after an independent audit (see §6).

---

## 1. Applied standards

| Standard | Role in this project | Where applied (evidence) |
| -------- | -------------------- | ------------------------ |
| ISO/IEC/IEEE 12207:2026 | Software lifecycle, development, operation, maintenance process | Phase roadmap + Part H implementation protocol (BLUEPRINT); `docs/requirements/*`; `AGENTS.md` workflow; CI in `.github/workflows/ci.yml` |
| ISO/IEC/IEEE 29148:2018 | Requirements engineering, testable requirements, traceability | `docs/requirements/business-requirements.md`, `functional-requirements.md`, `non-functional-requirements.md`, `acceptance-criteria.md`; `docs/compliance/requirements-traceability.md` |
| ISO/IEC 25010:2023 | Product quality model — maintainability, reliability, security, performance | `docs/requirements/non-functional-requirements.md` (NFR-1xx/2xx); `docs/backend/testing-strategy.md`; performance suite `tests/Performance/BenchmarkTest.php` |
| ISO/IEC 27001:2022 | Information security risk management (ISMS principles) | `docs/security/security-requirements.md`; `docs/compliance/risk-register.md`; `config/security.php` (rate limits, redaction, MFA); `docs/operations/deployment.md` security checklist |
| ISO 9241-210:2019 | Human-centred design process for interactive systems | `docs/frontend/design-system.md` (user journeys, states); `docs/frontend/accessibility-checklist.md`; UI/UX research notes in Phase 03 |
| ISO/IEC 40500:2025 (WCAG 2.2) | Web accessibility criteria | `docs/frontend/accessibility-checklist.md`; a11y tests in frontend Phase 13; NFR-400 series |

---

## 2. Compliance model in force

```
ISO / Industry Standards
          │  (this document maps them)
          ▼
Project Policies & Quality Requirements  (docs/requirements/*, config/security.php)
          ▼
Architecture / Business / UI Specifications (docs/architecture/*, docs/business/*)
          ▼
Implementation Rules + Automated Tests  (AGENTS.md, tests/, frontend test suite)
          ▼
Code Review + QA Evidence (docs/compliance/review-evidence/)
          ▼
Release Approval + Audit Records  (release notes, traceability matrix)
```

---

## 3. Evidence model per standard-driven requirement

Each requirement carries:

1. A requirement ID (BR/FR/NFR/AC).
2. An implementation pointer (module/service/route/page).
3. A verification pointer (test file or QA record).

The pointer chain is maintained in `docs/compliance/requirements-traceability.md`.
New work must add all three; a merge without a verification pointer is not compliant.

---

## 4. What "ISO-ready" does not claim

- Applying ISO 27001 principles is **not** certification under ISO/IEC 27001.
- Meeting WCAG 2.2 AA checks per release gate is a conformance effort, not an
  audited conformance certificate.
- These mappings are reviewed at each release gate; unresolved high-risk items block
  release (BLUEPRINT Part F §Definition of Done).

---

## 5. Review cadence

| Activity | Cadence | Evidence artifact |
| -------- | ------- | ----------------- |
| Requirements ↔ code traceability drift check | Per feature/phase | `requirements-traceability.md` |
| Security risk register review | Quarterly | `risk-register.md` |
| Accessibility conformance review | Per frontend release | `frontend/accessibility-checklist.md` |
| Dependency/secret scan | Every merge (CI) | CI run record |
| Backup restore drill | Quarterly | `operations/deployment.md` + drill log |

---

## 6. Certification note

No ISO certification is claimed for this platform. If certification is pursued, an
independent assessment and its findings replace this document as authoritative
evidence.