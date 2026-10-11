# Frontend Accessibility Checklist (WCAG 2.2 AA)

Status: **Implemented** (frontend track). Target: WCAG 2.2 Level AA for both portals,
verified by keyboard review, automated axe checks and the component conventions below.

Related: `docs/frontend/architecture.md` (rules), `docs/frontend/design-system.md`
(components), `docs/requirements/non-functional-requirements.md` (NFR-400 series),
`docs/requirements/acceptance-criteria.md` (AC-504).

## How this is verified

- **Keyboard-only** passes over each portal (focus visible, logical order, no traps).
- **axe-core** scans in the Playwright E2E suite (`npm run test:e2e`) on the login,
  dashboard, notifications and admin access pages — the scans must report no violations.
- Manual review against the checklist below for new components and pages.

## Perceivable

- [x] Text contrast meets AA (4.5:1 body, 3:1 large) in light and dark themes — tokens
      are chosen to satisfy AA against their intended surfaces.
- [x] Colour is never the sole carrier of meaning: status uses `StatusBadge` (text label)
      alongside tone, and unread notifications pair a dot with an "Unread" badge.
- [x] Focus is always visible: `:focus-visible` renders a 2px `--ring` outline globally
      (`resources/css/app.css`).
- [x] Form fields have programmatic labels; `FormField` links `label`/description/error via
      `for` and `aria-describedby` and sets `aria-invalid` on error.
- [x] Images/icons that convey meaning have text alternatives; decorative icons use
      `aria-hidden="true"` (e.g. the notification bell icon).

## Operable

- [x] Everything interactive is reachable and operable by keyboard (buttons, links,
      checkboxes, selects, tabs).
- [x] Dialogs (`ConfirmDialog`) trap focus, restore focus to the trigger on close, close on
      `Escape`, and expose `role="dialog"` + `aria-modal="true"`.
- [x] The notification bell is a labelled button (`aria-label`, `aria-haspopup`,
      `aria-expanded`) that closes on `Escape` and outside click, and its menu items are
      plain buttons.
- [x] No keyboard traps; focus order follows visual order.
- [x] Data tables use a `<caption>` (`DataTable` sets an `sr-only` caption and column
      headers with `scope="col"`).

## Understandable

- [x] `lang` is set on `<html>`; page titles update per route (`router.afterEach`).
- [x] Validation and API errors are surfaced as text (`Alert`, `InlineError`,
      `useAsyncAction` toasts) and announced via `role`/live regions where relevant
      (`Spinner` uses `role="status"`).
- [x] Destructive or irreversible actions require an explicit confirmation dialog and,
      where applicable, a reason.

## Robust

- [x] Components use native semantics first (`button`, `input`, `select`, `fieldset`,
      `legend`, `table`) and only add ARIA to fill gaps.
- [x] Grouped checkboxes are wrapped in `fieldset`/`legend`
      (`PermissionChecklist`, the role selector in `AdminUsersPage`).
- [x] Route guards reflect backend policy but never act as the authorization boundary.

## Known gaps / follow-ups

- [ ] Automated axe coverage currently targets the login, dashboard, notifications and
      admin access pages; extend to the remaining admin pages as they stabilise.
- [ ] Screen-reader smoke test on VoiceOver/NVDA is manual and tracked per release.
