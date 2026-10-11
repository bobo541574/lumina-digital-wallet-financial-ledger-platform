import { defineConfig, devices } from '@playwright/test';

const PORT = Number(process.env.E2E_PORT ?? 8123);
const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${PORT}`;

/**
 * End-to-end tests run against a real Laravel build (Postgres + seeded demo data)
 * served by `npm run e2e:server`. Set `E2E_BASE_URL` to point at an already-running
 * instance (e.g. the Herd site) and the managed web server is skipped.
 */
export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : [['list']],
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            // Use the full Chromium build (new headless) rather than the separate
            // chrome-headless-shell download.
            use: { ...devices['Desktop Chrome'], channel: 'chromium' },
        },
    ],
    webServer: process.env.E2E_BASE_URL
        ? undefined
        : {
              command: 'npm run e2e:server',
              url: `${baseURL}/login`,
              reuseExistingServer: !process.env.CI,
              timeout: 240_000,
              stdout: 'pipe',
              stderr: 'pipe',
          },
});
