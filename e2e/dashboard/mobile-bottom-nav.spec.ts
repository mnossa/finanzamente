import { test, expect } from '@playwright/test';

test.describe('Bottom navigation mobile', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('mostra le macro aree di default e la voce Altro senza FAB nella bar', async ({ page }) => {
        await page.goto('/dashboard');

        const bottomNav = page.getByRole('navigation', { name: 'Navigazione rapida' });

        await expect(bottomNav.getByRole('link', { name: 'Dashboard' })).toBeVisible();
        await expect(bottomNav.getByRole('link', { name: 'Movimenti' })).toBeVisible();
        await expect(bottomNav.getByRole('link', { name: 'Patrimonio' })).toBeVisible();
        await expect(bottomNav.getByRole('link', { name: 'Pianificazione e risparmio' })).toBeVisible();
        await expect(bottomNav.getByRole('button', { name: 'Altro' })).toBeVisible();
        await expect(bottomNav.getByTestId('mobile-primary-fab')).toHaveCount(0);
        await expect(page.getByTestId('mobile-primary-fab')).toBeVisible();
    });

    test('su desktop non mostra sticky bar né FAB floating', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto('/dashboard');

        await expect(page.getByRole('navigation', { name: 'Navigazione rapida' })).toHaveCount(0);
        await expect(page.getByTestId('mobile-primary-fab')).toHaveCount(0);
    });
    test('Altro apre il menu laterale', async ({ page }) => {
        await page.goto('/dashboard');

        await page.getByRole('button', { name: 'Altro' }).click();
        await expect(page.locator('aside').getByText('Panoramica')).toBeVisible();
    });
});
