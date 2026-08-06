import { test, expect } from '@playwright/test';

/**
 * Test E2E — Analisi (patrimonio / cashflow / spese)
 * Pagine lettura: nessun FAB «Nuova transazione» (come profilo).
 */
test.describe('Analisi', () => {
    test('la pagina patrimonio si carica', async ({ page }) => {
        await page.goto('/analisi/patrimonio');
        await expect(page).toHaveURL(/\/analisi\/patrimonio/);
        await expect(page).toHaveTitle(/Patrimonio/i);
    });

    test('su mobile non mostra FAB nuova transazione', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/analisi/patrimonio');

        await expect(page.getByTestId('mobile-primary-fab')).toHaveCount(0);
    });
});
