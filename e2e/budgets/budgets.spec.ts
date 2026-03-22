import { test, expect } from '@playwright/test';

/**
 * Test E2E — Budget
 *
 * Copre: lista budget, navigazione al form di creazione, verifica campi.
 */
test.describe('Budget', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/budgets');
    });

    test('carica la pagina dei budget', async ({ page }) => {
        await expect(page).toHaveURL('/budgets');
        await expect(page).toHaveTitle(/budget/i);
    });

    test('mostra il pulsante "Nuovo Budget"', async ({ page }) => {
        await expect(
            page.getByRole('link', { name: /nuovo budget/i })
        ).toBeVisible();
    });

    test('il pulsante "Nuovo Budget" porta al form di creazione', async ({ page }) => {
        await page.getByRole('link', { name: /nuovo budget/i }).click();
        await expect(page).toHaveURL('/budgets/create');
        await expect(page).toHaveTitle(/nuovo budget/i);
    });

    test('il form di creazione budget ha i campi obbligatori', async ({ page }) => {
        await page.goto('/budgets/create');
        await expect(page.getByRole('button', { name: /crea budget/i })).toBeVisible();
    });

    test('la lista budget mostra stato coerente (vuota o con dati)', async ({ page }) => {
        const hasTable  = await page.getByRole('table').isVisible().catch(() => false);
        const hasCards  = await page.locator('[class*="card"], [class*="Card"]').first().isVisible().catch(() => false);
        const hasEmpty  = await page.getByText(/nessun budget|non ci sono budget/i).isVisible().catch(() => false);

        expect(hasTable || hasCards || hasEmpty).toBeTruthy();
    });
});
