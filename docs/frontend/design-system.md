# Frontend Design System

Status: **Implemented** (frontend track). Source of truth for the design tokens,
reusable components and UI conventions used by the customer and admin portals.

- Tokens live in `resources/css/app.css` (`@theme` + `:root`/`.dark` variables).
- Primitives live in `resources/js/components/ui/`.
- Composite/domain components live in `resources/js/components/{layout,financial,admin}/`.
- Money, status and permission helpers live in `resources/js/lib/` and `resources/js/services/`.

The rules here are enforced by review; `npm run lint`, `npm run typecheck` and
`npm run test:unit` must pass before merge (see `docs/frontend/architecture.md`).

## 1. Design tokens

Components must reference tokens through Tailwind utilities (e.g. `bg-background`,
`text-muted-foreground`, `rounded-lg`, `border-border`) — never raw colour, radius or
font values. Light and dark themes are the same token names under `:root` and `.dark`,
so the whole product re-themes from one file.

| Token | Utility | Purpose |
| --- | --- | --- |
| `--background` / `--foreground` | `bg-background`, `text-foreground` | Page surface and text |
| `--card` / `--card-foreground` | `bg-card`, `text-card-foreground` | Raised surfaces (cards, dialogs, tables) |
| `--muted` / `--muted-foreground` | `bg-muted`, `text-muted-foreground` | Subtle fills and secondary text |
| `--primary` / `--primary-foreground` | `bg-primary`, `text-primary-foreground` | Primary actions and focus accents |
| `--secondary`, `--accent` (+ foregrounds) | `bg-secondary`, `bg-accent` | Secondary/ghost actions, hover states |
| `--destructive` (+ foreground) | `text-destructive`, `bg-destructive` | Errors and irreversible actions |
| `--success`, `--warning` (+ foregrounds) | `text-success`, `text-warning` | Status tones |
| `--border`, `--input`, `--ring` | `border-border`, `border-input`, `ring-ring` | Borders, inputs, focus rings |
| `--radius` (+ sm/md/lg/xl) | `rounded-sm`…`rounded-xl` | Corner radius scale |
| `--font-sans`, `--font-mono` | `font-sans`, `font-mono` | UI and identifier/monospace text |

Theme is applied by `useTheme()` (`resources/js/composables/useTheme.ts`), which toggles
`<html class="dark">`. It is initialised before mount in `resources/js/app.ts` and
pre-applied in the Blade shell (`resources/views/app.blade.php`) to avoid a flash.

## 2. Component inventory

### UI primitives (`components/ui/`)

| Component | Use |
| --- | --- |
| `Button` | Variants `primary`/`outline`/`ghost`/`destructive`, sizes, `loading` |
| `Card` | Tokenised surface container |
| `Input`, `Label`, `FormField`, `InlineError` | Accessible form fields; `FormField` wires label/description/error ids |
| `Select`, `CurrencySelect`, `SearchInput` | Selects and search input |
| `MoneyInput`, `MoneyText`, `AmountDisplay` | Money entry/display in minor units |
| `Badge`, `StatusBadge` | Status/tonal chips; `StatusBadge` maps a status string to a tone + label |
| `Alert`, `RetryPanel`, `Spinner`, `Skeleton`, `EmptyState` | Loading, empty and error states |
| `DataTable`, `Pagination` | Tabular data + cursor pagination controls |
| `FilterBar` | Filter row with a "clear filters" affordance |
| `ConfirmDialog` | Accessible modal (focus trap, Escape, optional reason); supports a default slot body |
| `PermissionGate` | Renders a slot only when the user holds the permission(s) |
| `ToastContainer` | Toast host driven by `useToast()` |

### Composite components

- `components/layout/`: `PageHeader`, `ResourceTable`, `ThemeToggle`, `NotificationBell`.
- `components/financial/`: `WalletBalanceCard`, `TransactionRow`, `TransactionTimeline`, `ReceiptPanel`.
- `components/admin/`: `ReviewPanel`, `AuditTimeline`, `ReconciliationMismatchCard`, `PermissionChecklist`.

## 3. Conventions

- **Money** is always an integer in minor units. Format with
  `lib/format/money.ts` / `MoneyText` / `AmountDisplay`; never do float arithmetic in the UI.
- **Status** is rendered via `StatusBadge` and `lib/status.ts` so tones are consistent
  across portals (`succeeded`, `failed`, `pending`, `reconciling`, …).
- **Async actions** use `useAsyncAction()` (shared `pending`, error capture, toast) and
  **list loading** uses `useAdminList()`; keep page components thin.
- **Dates** use `lib/format/date.ts` (`formatRelativeTime`) for relative times.
- **Authorization** is UX only. Hide actions with `PermissionGate`, but the backend is the
  enforcement boundary (see `docs/business/permissions-matrix.md`).
- **Errors** surface through `lib/errors.ts` (`apiErrorMessage`, `apiErrorCode`), which
  unwraps the backend error envelope.
- **Tokens only** — no raw colour/spacing/type values in components.

## 4. Generated API types

`npm run types:api` runs `openapi-typescript` over `docs/architecture/api/openapi.yaml`
and writes `resources/js/types/generated/api.ts` (committed). The hand-written resource
types in `resources/js/types/api.ts` are the app-facing layer; `resources/js/types/api.contract.ts`
asserts at compile time that they stay assignable to the generated schema, so contract
drift fails `npm run typecheck`. CI re-runs the generator and fails if the committed file
is stale.

## 5. Verification

- `npm run lint` (ESLint + Vue), `npm run typecheck` (vue-tsc), `npm run test:unit` (Vitest).
- Accessibility: `docs/frontend/accessibility-checklist.md`; automated axe checks in the
  Playwright E2E suite (`e2e/`).
