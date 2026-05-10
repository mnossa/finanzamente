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

    test('la lista transazioni mostra stato vuoto o righe con azioni', async ({ page }) => {
        // Stato coerente: empty state oppure almeno una riga transazione con link azione
        const emptyState = page.getByText(/nessuna transazione trovata/i).first();
        const rowLinks = page.getByRole('link').filter({ has: page.locator('[title]') });

        await expect
            .poll(async () => {
                const emptyVisible = await emptyState.isVisible().catch(() => false);
                const rowsCount = await rowLinks.count();
                return emptyVisible || rowsCount > 0;
            }, { timeout: 10_000 })
            .toBeTruthy();
    });

    test('i filtri di ricerca sono presenti', async ({ page }) => {
        // I filtri sono elementi <select> (combobox) nella barra filtri
        await expect(page.getByRole('combobox').first()).toBeVisible();
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
});
