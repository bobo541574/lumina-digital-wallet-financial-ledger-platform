import { expect, expectNoA11yViolations, login, test } from './fixtures';

test.describe('Authentication', () => {
    test('a customer can sign in and land on the dashboard', async ({ page }) => {
        await login(page, 'customer');

        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    });

    test('an admin lands on the admin overview', async ({ page }) => {
        await login(page, 'admin');
        await page.goto('/admin');

        await expect(page.getByRole('heading', { name: 'Admin overview' })).toBeVisible();
    });

    test('invalid credentials show an error and stay on the login page', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@example.com');
        await page.getByLabel('Password').fill('wrong-password');
        await page.getByRole('button', { name: 'Sign in' }).click();

        await expect(page.getByRole('alert')).toBeVisible();
        await expect(page).toHaveURL(/\/login$/);
    });

    test('the login page has no accessibility violations', async ({ page }) => {
        await page.goto('/login');
        await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();

        await expectNoA11yViolations(page);
    });
});
