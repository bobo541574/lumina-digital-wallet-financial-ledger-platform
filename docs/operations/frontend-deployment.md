# Frontend Deployment

How the Vue 3 SPA is built, configured and deployed. Backend/infrastructure
deployment lives in `deployment.md`; this document covers the frontend track
(Phase 14).

## Build

```bash
npm ci
npm run build        # vite build → public/build (hashed, versioned assets)
```

- The Laravel Vite plugin writes a manifest and hashed filenames, so a new deploy
  invalidates old assets automatically.
- Production builds drop `console`/`debugger` and emit no source maps. Opt in with
  `VITE_BUILD_SOURCEMAP=true` when uploading maps to an error tracker.

## Environment configuration

Frontend settings are baked in at build time via `VITE_*` variables. The CI job
builds with the committed defaults; production builds must set the real values in
the deploy environment before `npm run build`.

| Variable | Default | Purpose |
| --- | --- | --- |
| `VITE_API_BASE_URL` | `/api/v1` (same origin) | Absolute API origin for split-host deploys; leave empty when the SPA is served by the same Laravel app. |
| `VITE_REVERB_APP_KEY` / `VITE_REVERB_HOST` / `VITE_REVERB_PORT` / `VITE_REVERB_SCHEME` | empty | Realtime WebSocket client. Empty key disables realtime and the app falls back to polling. |
| `VITE_BUILD_SOURCEMAP` | `false` | Emit source maps in the production bundle. |

> Values are compiled into the JS bundle — never put secrets here. Only the public
> Reverb app key is safe to expose.

## Security headers & CSP

`App\Modules\Shared\Http\Middleware\SecurityHeaders` (registered globally in
`bootstrap/app.php`) always sends:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`
- `Cross-Origin-Opener-Policy: same-origin`

Opt-in via `config/security.php` (`SECURITY_*` env):

- **HSTS** (`SECURITY_HSTS=true`) — only sent on HTTPS requests.
- **CSP** (`SECURITY_CSP_ENABLED=true`) — nonce-based; the Vite tags and the inline
  theme bootstrap in `resources/views/app.blade.php` carry the nonce. Start with
  `SECURITY_CSP_REPORT_ONLY=true` to observe violations before enforcing. Add
  external origins (absolute API, dedicated WebSocket host) via
  `SECURITY_CSP_CONNECT_EXTRA` (comma-separated).

## Caching & SPA routing

- **Asset versioning**: hashed filenames + manifest; long-lived immutable caching
  is safe. `public/.htaccess` sets `Cache-Control: public, max-age=31536000,
  immutable` for `/build/*` and `no-cache` for `*.html` (Apache). For Nginx:

  ```nginx
  location /build/ { add_header Cache-Control "public, max-age=31536000, immutable"; }
  location = / {   add_header Cache-Control "no-cache"; }
  ```

- **SPA fallback**: every non-API, non-health route returns the Blade shell
  (`routes/web.php`, route `spa`). Delete/override the `try_files` fallback so it
  points at `index.php` (default Laravel `public/.htaccess` already does this).

## Smoke tests

`e2e/smoke.spec.ts` covers the critical path (shell boots without console errors,
customer sign-in → dashboard with no axe violations). Run it against any
environment:

```bash
E2E_BASE_URL=https://wallet.example.com npm run e2e:smoke
```

CI runs the full `e2e` suite on every push/PR.

## Rollout & rollback

1. Build assets (`npm ci && npm run build`) as part of the release artifact.
2. Deploy atomically; keep the previous `public/build` so rollback is a pointer
   swap. The HTML shell is `no-cache`, so clients pick up new hashes immediately.
3. Smoke test the deployed URL (`npm run e2e:smoke`).
4. Roll back by redeploying the previous release artifact; no data migration is
   tied to the frontend.
