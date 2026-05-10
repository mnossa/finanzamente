import { test, expect } from '@playwright/test';

/**
 * Test E2E — Transazioni
 *
 * Copre: lista transazioni, filtri, navigazione al form di creazione.
 */
test.describe('Transazioni', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/transazioni');
    });

    test('la pagina transazioni si carica', async ({ page }) => {
        await expect(page).toHaveURL('/transazioni');
        await expect(page).toHaveTitle(/transazioni/i);
    });

    test('esiste il link per creare una nuova transazione', async ({ page }) => {
        await expect(page.locator('a[href*="/transazioni/crea"]').first()).toBeVisible();
    });

    test('il link nuova transazione porta al form', async ({ page }) => {
        await page.locator('a[href*="/transazioni/crea"]').first().click();
        await expect(page).toHaveURL('/transazioni/crea');
        await expect(page).toHaveTitle(/nuova transazione/i);
    });

    test('la lista mostra stato vuoto o righe', async ({ page }) => {
        // Stato coerente: empty state o righe con link azione
        const emptyState = page.getByText(/nessuna transazione/i).first();
        const rows = page.locator('table tbody tr, [data-row], ul li a[href*="/transazioni/"]');

        await expect
            .poll(async () => {
                const emptyVisible = await emptyState.isVisible().catch(() => false);
                const rowsCount = await rows.count();
                return emptyVisible || rowsCount > 0;
            }, { timeout: 10_000 })
            .toBeTruthy();
    });

    test('i filtri di ricerca sono presenti', async ({ page }) => {
        await expect(page.getByRole('combobox').first()).toBeVisible();
    });

    test('navigazione paginazione funziona se ci sono più pagine', async ({ page }) => {
        const pagination = page.getByRole('navigation', { name: /paginazione/i });
        if (await pagination.isVisible()) {
            const nextLink = pagination.locator('a[href*="page=2"]');
            if (await nextLink.isVisible()) {
                await nextLink.click();
                await expect(page).toHaveURL(/page=2/);
            }
        }
    });
});
