#!/usr/bin/env bash
#
# Boot a throwaway instance for the Playwright E2E suite: a dedicated database
# (never the dev DB), fresh schema, demo seed, built assets, then `artisan serve`.
# Used as Playwright's `webServer.command`. Override via env:
#
#   E2E_DB_DATABASE  database name (default: lumina_e2e)
#   E2E_PORT         listen port   (default: 8123)
set -euo pipefail

export DB_DATABASE="${E2E_DB_DATABASE:-lumina_e2e}"
export E2E_PORT="${E2E_PORT:-8123}"
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

# The suite signs in many times from one IP; relax the auth/money/admin throttles.
export RATE_LIMIT_AUTH="${RATE_LIMIT_AUTH:-1000}"
export RATE_LIMIT_MONEY="${RATE_LIMIT_MONEY:-1000}"
export RATE_LIMIT_ADMIN="${RATE_LIMIT_ADMIN:-1000}"

# No WebSocket server runs for E2E; force realtime off so the build never tries
# to open a Reverb socket. The SPA falls back to REST polling automatically.
export BROADCAST_CONNECTION="${BROADCAST_CONNECTION:-null}"
export VITE_REVERB_APP_KEY="${VITE_REVERB_APP_KEY:-}"

echo "[e2e] preparing database ${DB_DATABASE}"
createdb -h "${DB_HOST:-127.0.0.1}" -U "${DB_USERNAME:-postgres}" "${DB_DATABASE}" 2>/dev/null || true

php artisan migrate:fresh --force --no-interaction
php artisan db:seed --force --no-interaction

echo "[e2e] building assets"
npm run build

echo "[e2e] serving on http://127.0.0.1:${E2E_PORT}"
exec php artisan serve --host=127.0.0.1 --port="${E2E_PORT}" --no-reload
