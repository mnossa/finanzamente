import { test, expect } from '@playwright/test';

test.describe('Bottom navigation mobile', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('mostra le macro aree di default e la voce Altro', async ({ page }) => {
        await page.goto('/dashboard');

        const bottomNav = page.getByRole('navigation', { name: 'Navigazione rapida' });

        await expect(bottomNav.getByRole('link', { name: 'Dashboard' })).toBeVisible();
        await expect(bottomNav.getByRole('link', { name: 'Conti e movimenti' })).toBeVisible();
        await expect(bottomNav.getByRole('link', { name: 'Patrimonio' })).toBeVisible();
        await expect(bottomNav.getByRole('link', { name: 'Pianificazione e risparmio' })).toBeVisible();
        await expect(bottomNav.getByRole('button', { name: 'Altro' })).toBeVisible();
    });

    test('Altro apre il menu laterale', async ({ page }) => {
        await page.goto('/dashboard');

        await page.getByRole('button', { name: 'Altro' }).click();
        await expect(page.locator('aside').getByText('Panoramica')).toBeVisible();
    });
});
