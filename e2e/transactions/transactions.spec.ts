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
        // Stato vuoto oppure righe linkate a /transazioni/:id
        const emptyState = page.getByText(/nessuna transazione trovata/i).first();
        // Le righe sono link verso /transazioni/:id (tutta la riga è linkabile su mobile)
        const rowLinks = page.locator('a[href*="/transazioni/"]').filter({ hasNot: page.locator('[href="/transazioni"]') });

        await expect
            .poll(async () => {
                const emptyVisible = await emptyState.isVisible().catch(() => false);
                const rowsCount = await rowLinks.count();
                return emptyVisible || rowsCount > 0;
            }, { timeout: 15_000 })
            .toBeTruthy();
    });

    test('i filtri di ricerca sono presenti', async ({ page }) => {
        // Il pannello filtri è collassabile: verifichiamo che esista il trigger (sempre visibile)
        // oppure che i combobox siano direttamente visibili se il pannello è già aperto
        const filterTrigger = page.getByTestId('filter-summary');
        const combobox = page.getByRole('combobox').first();

        await expect
            .poll(async () => {
                const triggerVisible = await filterTrigger.isVisible().catch(() => false);
                const comboboxVisible = await combobox.isVisible().catch(() => false);
                return triggerVisible || comboboxVisible;
            }, { timeout: 8_000 })
            .toBeTruthy();
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
        await visibleHrefLocator(page, '/transazioni/crea').click();
        await expect(page).toHaveURL('/transazioni/crea');

        // Le opzioni aggiuntive (incluso FX) sono in un pannello collassabile — apriamo prima
        const extraDetails = page.locator('details').filter({ hasText: /opzioni aggiuntive/i });
        if (await extraDetails.count() > 0) {
            const isOpen = await extraDetails.getAttribute('open');
            if (isOpen === null) {
                await extraDetails.locator('summary').click();
            }
        }

        const toggle = page.getByRole('button', { name: /valuta diversa.*conto|pagato in valuta/i });
        await expect(toggle).toBeVisible({ timeout: 5_000 });

        // Apre la sezione FX e verifica che compaiano i tre campi di valuta
        await toggle.click();
        await expect(page.getByLabel(/importo originale/i)).toBeVisible();
        await expect(page.getByLabel(/valuta originale|valuta/i).first()).toBeVisible();
        await expect(page.getByLabel(/cambio manuale/i)).toBeVisible();
    });

    test('mostra l\'anteprima del cambio quando si seleziona una valuta diversa dal conto', async ({ page }) => {
        await visibleHrefLocator(page, '/transazioni/crea').click();
        await expect(page).toHaveURL('/transazioni/crea');

        // Apri opzioni aggiuntive se chiuse
        const extraDetails = page.locator('details').filter({ hasText: /opzioni aggiuntive/i });
        if (await extraDetails.count() > 0) {
            const isOpen = await extraDetails.getAttribute('open');
            if (isOpen === null) {
                await extraDetails.locator('summary').click();
            }
        }

        await page.getByRole('button', { name: /valuta diversa.*conto|pagato in valuta/i }).click();
        // Default è la valuta dell'utente: forziamo GBP per garantire mismatch col conto principale (EUR)
        await page.getByLabel(/valuta originale|valuta/i).first().selectOption('GBP');

        // L'hint compare (eventualmente con stato "Calcolo cambio…" che si stabilizza)
        const hint = page.getByTestId('fx-preview-hint');
        await expect(hint).toBeVisible({ timeout: 5_000 });
        await expect(hint).toContainText(/cambio del giorno|calcolo cambio|=/i, { timeout: 10_000 });
    });

    test('il conto in valuta estera "Revolut GBP" è visibile nella select del form', async ({ page }) => {
        await visibleHrefLocator(page, '/transazioni/crea').click();
        await expect(page).toHaveURL('/transazioni/crea');

        const accountSelect = page.locator('#account_id');
        await expect(accountSelect).toBeVisible();

        // L'opzione esiste solo se il seeder E2E ha creato il conto Revolut GBP.
        // Verifichiamo che il name dell'opzione contenga "Revolut" e mostri (GBP) come valuta.
        await expect(accountSelect.locator('option', { hasText: /revolut/i })).toContainText(/gbp/i);
    });

    test('su mobile il link Importa è nel corpo pagina (toolbar sotto header)', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/transazioni');
        const importLink = visibleHrefLocator(page, '/transazioni/importa');
        await expect(importLink).toBeVisible();
        await importLink.click();
        await expect(page).toHaveURL(/\/transazioni\/importa/);
    });
});
