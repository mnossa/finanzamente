import { test, expect } from '@playwright/test';
import { visibleHrefLocator } from '../helpers';

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
        await expect(visibleHrefLocator(page, '/transazioni/crea')).toBeVisible();
    });

    test('il link nuova transazione porta al form', async ({ page }) => {
        await visibleHrefLocator(page, '/transazioni/crea').click();
        await expect(page).toHaveURL('/transazioni/crea');
        await expect(page).toHaveTitle(/nuova transazione/i);
    });

    test('la lista mostra stato vuoto o righe', async ({ page }) => {
        // Lista reale: card (no table); ogni riga espone link "Visualizza" verso /transazioni/:id
        const emptyState = page.getByText(/nessuna transazione trovata/i).first();
        const rowViewLinks = page.getByRole('link', { name: /^visualizza$/i });

        await expect
            .poll(async () => {
                const emptyVisible = await emptyState.isVisible().catch(() => false);
                const rowsCount = await rowViewLinks.count();
                return emptyVisible || rowsCount > 0;
            }, { timeout: 15_000 })
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

    test('il form di creazione espone il toggle "valuta diversa dal conto"', async ({ page }) => {
        await page.getByRole('link', { name: /nuova transazione/i }).first().click();
        await expect(page).toHaveURL('/transazioni/crea');

        const toggle = page.getByRole('button', { name: /valuta diversa dal conto/i });
        await expect(toggle).toBeVisible();

        // Apre la sezione FX e verifica che compaiano i tre campi di valuta
        await toggle.click();
        await expect(page.getByLabel(/importo originale/i)).toBeVisible();
        await expect(page.getByLabel(/valuta originale/i)).toBeVisible();
        await expect(page.getByLabel(/cambio manuale/i)).toBeVisible();
    });

    test('mostra l\'anteprima del cambio quando si seleziona una valuta diversa dal conto', async ({ page }) => {
        await page.getByRole('link', { name: /nuova transazione/i }).first().click();
        await expect(page).toHaveURL('/transazioni/crea');

        await page.getByRole('button', { name: /valuta diversa dal conto/i }).click();
        // Default è la valuta dell'utente: forziamo GBP per garantire mismatch col conto principale (EUR)
        await page.getByLabel(/valuta originale/i).selectOption('GBP');

        // L'hint compare (eventualmente con stato "Calcolo cambio…" che si stabilizza)
        const hint = page.getByTestId('fx-preview-hint');
        await expect(hint).toBeVisible({ timeout: 5_000 });
        await expect(hint).toContainText(/cambio del giorno|calcolo cambio/i, { timeout: 10_000 });
    });

    test('il conto in valuta estera "Revolut GBP" è visibile nella select del form', async ({ page }) => {
        await page.getByRole('link', { name: /nuova transazione/i }).first().click();
        await expect(page).toHaveURL('/transazioni/crea');

        const accountSelect = page.locator('#account_id');
        await expect(accountSelect).toBeVisible();

        // L'opzione esiste solo se il seeder E2E ha creato il conto Revolut GBP.
        // Verifichiamo che il name dell'opzione contenga "Revolut" e mostri (GBP) come valuta.
        await expect(accountSelect.locator('option', { hasText: /revolut/i })).toContainText(/gbp/i);
    });
});
