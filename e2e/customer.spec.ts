import { expect, expectNoA11yViolations, test } from './fixtures';

test.describe('Customer portal', () => {
    test('the dashboard lists the customer wallets', async ({ authedPage }) => {
        await authedPage.goto('/app');

        await expect(authedPage.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    });

    test('a customer can open the notifications center', async ({ authedPage }) => {
        await authedPage.goto('/app/notifications');

        await expect(authedPage.getByRole('heading', { name: 'Notifications' })).toBeVisible();
    });

    test('the dashboard has no accessibility violations', async ({ authedPage }) => {
        await authedPage.goto('/app');
        await expect(authedPage.getByRole('heading', { name: 'Dashboard' })).toBeVisible();

        await expectNoA11yViolations(authedPage);
    });

    test('the notifications center has no accessibility violations', async ({ authedPage }) => {
        await authedPage.goto('/app/notifications');
        await expect(authedPage.getByRole('heading', { name: 'Notifications' })).toBeVisible();

        await expectNoA11yViolations(authedPage);
    });
});
