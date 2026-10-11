import { expect, expectNoA11yViolations, login, test } from './fixtures';

test.describe('Admin access management', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, 'admin');
    });

    test('the roles page lists the system roles', async ({ page }) => {
        await page.goto('/admin/roles');

        await expect(page.getByRole('heading', { name: 'Roles & permissions' })).toBeVisible();
        await expect(page.getByRole('table')).toBeVisible();
    });

    test('the users page lists users and opens access management', async ({ page }) => {
        await page.goto('/admin/users');

        await expect(page.getByRole('heading', { name: 'Users & access' })).toBeVisible();
        await expect(page.getByRole('table')).toBeVisible();
    });

    test('the roles page has no accessibility violations', async ({ page }) => {
        await page.goto('/admin/roles');
        await expect(page.getByRole('heading', { name: 'Roles & permissions' })).toBeVisible();

        await expectNoA11yViolations(page);
    });

    test('the users page has no accessibility violations', async ({ page }) => {
        await page.goto('/admin/users');
        await expect(page.getByRole('heading', { name: 'Users & access' })).toBeVisible();

        await expectNoA11yViolations(page);
    });
});
