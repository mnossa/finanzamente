import { test, expect } from '@playwright/test';

test.describe('Galleria widget — mobile', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test.beforeEach(async ({ page }) => {
        await page.goto('/widget-formule/galleria');
        await expect(page).toHaveURL(/\/widget-formule\/galleria/);
    });

    test('mostra toolbar mobile e pulsante Installa a tutta larghezza', async ({ page }) => {
        await expect(page.getByRole('link', { name: 'I miei widget' }).first()).toBeVisible();
        await expect(page.getByRole('button', { name: /^installa$/i }).first()).toBeVisible();
        await expect(page.getByRole('button', { name: /^anteprima$/i }).first()).toBeVisible();
    });

    test('il FAB mobile porta a creazione widget', async ({ page }) => {
        await page.getByRole('link', { name: /nuovo widget/i }).click();
        await expect(page).toHaveURL(/\/widget-formule\/crea/);
    });
});
