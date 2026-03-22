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
        await page.goto('/transactions');
    });

    test('carica la pagina delle transazioni', async ({ page }) => {
        await expect(page).toHaveURL('/transactions');
        await expect(page).toHaveTitle(/transazioni/i);
    });

    test('mostra il pulsante "Nuova Transazione"', async ({ page }) => {
        await expect(
            page.getByRole('link', { name: /nuova transazione/i })
        ).toBeVisible();
    });

    test('il pulsante "Nuova Transazione" porta al form di creazione', async ({ page }) => {
        await page.getByRole('link', { name: /nuova transazione/i }).click();
        await expect(page).toHaveURL('/transactions/create');
        await expect(page).toHaveTitle(/nuova transazione/i);
    });

    test('la lista transazioni mostra messaggio vuoto o righe', async ({ page }) => {
        // Verifica che la pagina sia in uno stato coerente (empty state o tabella)
        const hasData = await page.getByRole('table').isVisible().catch(() => false);
        const hasEmpty = await page.getByText(/nessuna transazione|non ci sono transazioni|inizia aggiungendo/i).isVisible().catch(() => false);

        expect(hasData || hasEmpty).toBeTruthy();
    });

    test('i filtri di ricerca sono presenti', async ({ page }) => {
        // Il form di filtro deve essere presente
        const filterForm = page.locator('form, [role="search"]').first();
        await expect(filterForm).toBeVisible();
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
