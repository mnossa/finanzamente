import { test, expect } from '@playwright/test';

/**
 * Test E2E — Categorie
 *
 * Copre: lista, navigazione al form, creazione.
 */
test.describe('Categorie', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/categorie');
    });

    test('la pagina categorie si carica', async ({ page }) => {
        await expect(page).toHaveURL('/categorie');
        await expect(page).toHaveTitle(/categorie/i);
    });

    test('esiste il link per creare una nuova categoria', async ({ page }) => {
        await expect(page.locator('a[href*="/categorie/crea"]')).toBeVisible();
    });

    test('il link nuova categoria porta al form', async ({ page }) => {
        await page.locator('a[href*="/categorie/crea"]').first().click();
        await expect(page).toHaveURL('/categorie/crea');
        await expect(page).toHaveTitle(/nuova categoria/i);
    });

    test('il form di creazione ha il campo nome e il submit', async ({ page }) => {
        await page.goto('/categorie/crea');
        await expect(page.locator('input[name="name"]')).toBeVisible();
        await expect(page.locator('[type="submit"]')).toBeVisible();
    });

    test('crea una nuova categoria e appare nella lista', async ({ page }) => {
        const nomeCategoria = `Categoria E2E ${Date.now()}`;

        await page.goto('/categorie/crea');
        await page.locator('input[name="name"]').fill(nomeCategoria);

        // Tipo: può essere select o pulsanti radio-like
        const typeSelect = page.locator('select[name="type"]');
        if (await typeSelect.isVisible()) {
            await typeSelect.selectOption('expense');
        }

        await page.locator('[type="submit"]').click();

        await expect(page).toHaveURL(/\/categorie/, { timeout: 10_000 });
        await expect(page.getByText(nomeCategoria)).toBeVisible({ timeout: 8_000 });
    });
});
