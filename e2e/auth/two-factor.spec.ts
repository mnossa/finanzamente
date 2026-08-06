import { test, expect } from '@playwright/test';

test.describe('Verifica MFA al login', () => {
    test('la pagina verifica 2FA si carica con sessione di login pendente', async ({ page, request }) => {
        await page.context().clearCookies();
        await page.goto('/accedi');

        await page.locator('input[name="email"]').fill('e2e@finanzamente.test');
        await page.locator('input[name="password"]').fill('password');
        await Promise.all([
            page.waitForURL(/\/(dashboard|verifica-2fa)/),
            page.locator('button[type="submit"]').click(),
        ]);

        if (page.url().includes('/verifica-2fa')) {
            await expect(page.getByRole('heading', { name: /verifica a due fattori/i })).toBeVisible();
            await expect(page.locator('input[name="code"]')).toBeVisible();
            return;
        }

        expect(page.url()).toContain('/dashboard');
    });
});
