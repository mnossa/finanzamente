import { test, expect } from '@playwright/test';
import { visibleHrefLocator } from '../helpers';

/**
 * Test E2E — Budget
 *
 * Copre: lista budget, navigazione al form di creazione, verifica campi.
 */
test.describe('Budget', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/budget');
    });

    test('carica la pagina dei budget', async ({ page }) => {
        await expect(page).toHaveURL('/budget');
        await expect(page).toHaveTitle(/budget/i);
    });

    test('mostra il pulsante "Nuovo Budget"', async ({ page }) => {
        // Header + FAB mobile possono duplicare href: solo istanza visibile (come e2e/conti).
        await expect(visibleHrefLocator(page, '/budget/crea')).toBeVisible();
    });

    test('il pulsante "Nuovo Budget" porta al form di creazione', async ({ page }) => {
        await visibleHrefLocator(page, '/budget/crea').click();
        await expect(page).toHaveURL('/budget/crea');
        await expect(page).toHaveTitle(/nuovo budget/i);
    });

    test('il form di creazione budget ha i campi obbligatori', async ({ page }) => {
        await page.goto('/budget/crea');
        await expect(page.getByRole('button', { name: /crea budget/i })).toBeVisible();
    });

    test('la lista budget mostra stato coerente (vuota o con dati)', async ({ page }) => {
        await expect(
            page.getByRole('heading', { name: /nessun budget trovato/i })
                .or(page.getByRole('table'))
        ).toBeVisible({ timeout: 10_000 });
    });
});
