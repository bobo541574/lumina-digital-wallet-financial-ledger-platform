# Compliance & Review Evidence

This directory holds the compliance artefacts of the Lumina platform.

| Artefact | Purpose |
| -------- | ------- |
| `standards-mapping.md` | How each ISO/reference standard is applied and where evidence lives. |
| `requirements-traceability.md` | Requirement → implementation → verification chain (movement of money, authorization, ledger, reconciliation). |
| `risk-register.md` | Financial/security risks, controls, residual risk, review log. |
| `review-evidence/` | Signed evidence artefacts: test run records, benchmark results, review sign-offs. |

## Rules of record

- Never claim a test passed unless it was executed. Evidence artefacts must record
  the command, date, environment and a pass/fail result.
- Never claim ISO certification here or anywhere in the repo; that requires an
  independent audit.
- The traceability matrix is updated in the same merge as the requirement,
  implementation, or test it links.

## Certification note

This platform is built to be auditable against the listed standards, but holds no
certification claim of its own. Certification requires an independent assessment;
until then, these documents are the engineering evidence base.