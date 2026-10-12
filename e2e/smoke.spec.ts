import { expect, expectNoA11yViolations, login, test } from './fixtures';

/**
 * Critical-path smoke checks. Tagged `@smoke` so they can be pointed at any
 * environment (staging/production) via `E2E_BASE_URL`:
 *
 *   E2E_BASE_URL=https://wallet.example.com npm run e2e:smoke
 */
test.describe('Production smoke @smoke', () => {
    test('the app shell boots without console errors', async ({ page }) => {
        const errors: string[] = [];
        page.on('console', (message) => {
            if (message.type() === 'error') {
                errors.push(message.text());
            }
        });
        page.on('pageerror', (error) => errors.push(error.message));

        await page.goto('/login');
        await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();

        expect(errors).toEqual([]);
    });

    test('a customer can sign in and reach the dashboard', async ({ page }) => {
        await login(page, 'customer');

        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
        await expectNoA11yViolations(page);
    });
});
