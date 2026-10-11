# Monitoring

Companion to `docs/operations/deployment.md`. Alerts below map to risk register rows
and to NFR-4xx availability/performance requirements.

## Logging

- Monolog; PSR-3 structured. Sensitive fields redacted via `config/security.php`
  `redact_keys`.
- Financial events carry a `request_id`, `correlation_id` (idempotency key on money
  movements) linking API → services → ledger → outbox → provider.

## Metrics & alerts

| Alert | Condition | Why (risk) | Response |
| ----- | --------- | ---------- | -------- |
| `reconciliation_variance` | Wallet vs ledger mismatch detected | R-07 silent drift | Freeze touch actions; runbook `reconciliation-mismatch.md` |
| `webhook_quarantine` | Provider event quarantined | R-04 fake/duplicate event | Inspect signature+payload; runbook |
| `outbox_backlog` | Worker lag over threshold | R-08 lost notification | Scale worker; inspect consumer |
| `auth_fail_ratio` | Login/2FA failure spike | R-09 brute force | Verify limiter; review IPs |
| `partition_gap` | Missing month partition | R-12 | Run `outbox:process`? no — `PaymentEvents:ensure` |
| `withdrawal_timeout` | Withdrawals sitting in `reconciling` > threshold | R-05 | Confirm provider; resolve per runbook |
| `db_lag / queue_high` | Postgres replication/queue depth | R-11 | Failover/scale per deployment doc |
| `ledger_mutation_attempt` | Trigger blocked update/delete on ledger | R-06 intentional tampering | Investigate as fraud signal |

## Dashboards & boundaries

- Per-module error rate and latency (`money`, `auth`, `webhooks`, `admin`) — match the
  rate limiter names from `config/security.php`.
- Budgets: p95 < NS; reconcile drift must be zero per currency after resolution.

## Runbooks

Incident procedures live in `docs/runbooks/` and are linked from every alert above.