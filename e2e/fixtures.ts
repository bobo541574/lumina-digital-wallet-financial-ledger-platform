import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test as base } from '@playwright/test';

/** Demo credentials created by DatabaseSeeder's DemoSeeder. */
export const credentials = {
    customer: { email: 'test@example.com', password: 'password' },
    admin: { email: 'admin@example.com', password: 'password' },
} as const;

/** Sign in through the UI and wait for the destination route to render. */
export async function login(
    page: Page,
    user: keyof typeof credentials,
    expectedPath = '/app',
): Promise<void> {
    const { email, password } = credentials[user];

    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(new RegExp(`${expectedPath.replace(/\//g, '\\/')}(\\/)?$`));
}

/** Assert the current page has no axe violations for the WCAG 2.x A/AA rule sets. */
export async function expectNoA11yViolations(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(
        results.violations,
        results.violations
            .map((v) => `${v.id}: ${v.help} (${v.nodes.length} node(s))`)
            .join('\n'),
    ).toEqual([]);
}

/** Test fixture exposing a page that is already authenticated as a demo user. */
type Fixtures = {
    authedPage: Page;
};

export const test = base.extend<Fixtures>({
    authedPage: async ({ page }, use) => {
        await login(page, 'customer');
        await use(page);
    },
});

export { expect };
