# Frontend Architecture (frontend track, Phases 02–14)

Status: **Foundation implemented** (Phases 02–05). Portal feature work (Phases 06–12)
and E2E/a11y automation (Phases 13–14) remain. This document defines the target state
the frontend track must satisfy (BLUEPRINT Part E, ADR-0002 in the blueprint).

## Scope and stack

- **Portals**: Customer portal (register, wallets, transfer/deposit/withdraw, history),
  Admin portal (review, wallets overview, deposits, withdrawals, reconciliation, audit),
  Identity/IAM (login, 2FA, profile/settings).
- **Stack**: Vue 3 (Composition API `<script setup lang="ts">`), TypeScript, Pinia
  (state), Vue Router (route guards), Tailwind CSS v4 (design tokens), shadcn-vue-style
  primitives, Vitest + Vue Test Utils, Playwright for E2E (Phase 13).
- **Bootstrap**: Laravel Vite plugin wired (`resources/js/app.ts`); dev via
  `npm run dev`, production build via `npm run build`.
- **Realtime**: Laravel Reverb over WebSockets via `laravel-echo` + `pusher-js`
  (`resources/js/realtime/echo.ts`), with REST polling as the fallback and resync path.

## Non-negotiable rules

1. **Backend is the single source of truth.** UI renders what the API returns; it never
   stores authoritative balances or state. Realtime events only trigger re-fetch.
2. **Money renders as minor-unit integers → display formatting only** (currency
   formatter util using ISO-4217 codes); no float arithmetic in the UI
   (`resources/js/lib/format/money.ts`).
3. **Route guards mirror backend policies** (role/permission); guards are UX, not security.
4. **Accessibility (WCAG 2.2 AA)** for every component; keyboard operable; focus visible.
5. **Design tokens are the only raw values in components** — no magic colours/space/type
   (`resources/css/app.css` `@theme` tokens).

## Realtime notifications

The notification center stays REST-first: the API list and unread count are
authoritative, and the badge polls on an interval plus on tab-focus and `online`
events. When Reverb is configured the store *also* subscribes to the user's
private channel (`App.Models.User.{id}`) and listens for `.notification.created`,
treating each event purely as a signal to re-fetch. A socket reconnect also
triggers a resync. Realtime is opt-in and never load-bearing: without
`VITE_REVERB_APP_KEY` (or `BROADCAST_CONNECTION=reverb` on the backend) the app
runs on polling alone, which is what E2E does.

## Layout

- `resources/js/`
  - `components/ui/` (design-system primitives: Button, Card, Input, Label, Alert, Badge,
    Spinner, EmptyState, MoneyText, StatusBadge)
  - `components/layout/` (PageHeader, ResourceTable)
  - `layouts/` (CustomerLayout, AdminLayout)
  - `pages/` (`auth/`, `customer/`, `admin/`, `errors/`, `LandingPage`)
  - `composables/` (`useAdminList`)
  - `router/` (guards per portal: `requiresAuth`, `requiresAdmin`, `permission`)
  - `stores/` (Pinia: `auth`, `notifications`)
  - `realtime/` (`echo.ts` Echo/Reverb client; opt-in per environment)
  - `services/` (`api/client.ts` typed client + per-domain services)
  - `lib/` (`format/money`, `status`, `errors`, `idempotency`, `utils`)
  - `types/` (mirrors `docs/architecture/api/openapi.yaml`)

## Verification

- Vitest unit/component tests for money formatting, status mapping, the API client and
  components (`npm run test:unit`).
- `npm run typecheck` (vue-tsc) and `npm run lint` (ESLint) must pass.
- Playwright E2E covering the customer journey end to end (Phase 13).
- WAI-ARIA/axe a11y checks in CI (Phase 13).
- `npm run build` must succeed (Vite manifest contract) before merge.
- CI `frontend` job runs lint, typecheck, unit tests and build on every push/PR.
