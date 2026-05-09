import { test, expect } from '@playwright/test';

/**
 * Test E2E — Transazioni
 *
 * Copre: lista transazioni, filtri, navigazione al form di creazione.
 * La creazione completa richiede un conto e una categoria esistenti —
 * i test di CRUD completi dipendono dal seeder E2E.
 */
test.describe('Transazioni', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/transazioni');
    });

    test('carica la pagina delle transazioni', async ({ page }) => {
        await expect(page).toHaveURL('/transazioni');
        await expect(page).toHaveTitle(/transazioni/i);
    });

    test('mostra il pulsante "Nuova Transazione"', async ({ page }) => {
        // .first() perché il link appare sia nell'header che nell'empty state
        await expect(
            page.getByRole('link', { name: /nuova transazione/i }).first()
        ).toBeVisible();
    });

    test('il pulsante "Nuova Transazione" porta al form di creazione', async ({ page }) => {
        await page.getByRole('link', { name: /nuova transazione/i }).first().click();
        await expect(page).toHaveURL('/transazioni/crea');
        await expect(page).toHaveTitle(/nuova transazione/i);
    });

    test('la lista transazioni mostra messaggio vuoto o righe', async ({ page }) => {
        // Stato coerente: empty state oppure almeno una riga transazione con azioni
        const emptyState = page.getByText(/nessuna transazione trovata/i).first();
        const rowActions = page.locator('[title="Visualizza"]');

        await expect
            .poll(async () => {
                const emptyVisible = await emptyState.isVisible().catch(() => false);
                const rowsCount = await rowActions.count();
                return emptyVisible || rowsCount > 0;
            }, { timeout: 10_000 })
            .toBeTruthy();
    });

    test('i filtri di ricerca sono presenti', async ({ page }) => {
        // I filtri sono select element in un CardBox (non wrappati in un form)
        await expect(page.locator('select').first()).toBeVisible();
    });

    test('navigazione paginazione funziona se ci sono più pagine', async ({ page }) => {
        const pagination = page.getByRole('navigation', { name: /paginazione/i });
        // Se la paginazione esiste, verifica che funzioni
        if (await pagination.isVisible()) {
            const nextButton = pagination.getByRole('link', { name: /successiva|next|›/i });
            if (await nextButton.isVisible()) {
                await nextButton.click();
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
