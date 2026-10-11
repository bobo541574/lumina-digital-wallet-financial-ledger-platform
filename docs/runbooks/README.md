# Runbooks

Operational procedures triggered by alerts in `docs/operations/monitoring.md`. Each
runbook states **symptoms → safe first step → fix → confirmation → postmortem hook**. No
runbook may assume a state is corrected without re-checking the ledger invariant
(`sum(posted) == 0` per currency after resolution).

## Index

| Runbook | Trigger (alert) | Blocker status |
| ------- | --------------- | -------------- |
| `reconciliation-mismatch.md` | reconciliation_variance | Green (backend) |
| `webhook-quarantine.md` | webhook_quarantine | Green (backend) |
| `outbox-backlog.md` | outbox_backlog | Green (backend) |
| `withdrawal-timeout.md` | withdrawal_timeout | Green (backend) |
| `ledger-tampering.md` | ledger_mutation_attempt | Green (backend) |
| `frontend-deployment.md` | frontend build/release | Pending (frontend track) |