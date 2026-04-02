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
        // Verifica che la pagina sia in uno stato coerente (empty state o tabella con dati)
        await expect(
            page.getByRole('heading', { name: /nessuna transazione trovata/i })
                .or(page.getByRole('table'))
        ).toBeVisible({ timeout: 10_000 });
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
});
