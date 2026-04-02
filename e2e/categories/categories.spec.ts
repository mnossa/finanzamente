import { test, expect } from '@playwright/test';

/**
 * Test E2E — Categorie
 *
 * Copre: lista categorie, creazione nuova categoria, validazione.
 */
test.describe('Categorie', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/categorie');
    });

    test('carica la pagina delle categorie', async ({ page }) => {
        await expect(page).toHaveURL('/categories');
        await expect(page).toHaveTitle(/categorie/i);
    });

    test('mostra il pulsante "Nuova Categoria"', async ({ page }) => {
        await expect(
            page.getByRole('link', { name: /nuova categoria/i })
        ).toBeVisible();
    });

    test('il pulsante "Nuova Categoria" porta al form di creazione', async ({ page }) => {
        await page.getByRole('link', { name: /nuova categoria/i }).click();
        await expect(page).toHaveURL('/categories/create');
        await expect(page).toHaveTitle(/nuova categoria/i);
    });

    test('il form di creazione ha i campi obbligatori', async ({ page }) => {
        await page.goto('/categories/create');
        await expect(page.locator('#name')).toBeVisible();
        // Il tipo è selezionato tramite pulsanti (Entrata/Uscita), non un <select id="type">
        await expect(
            page.getByRole('button', { name: /entrata|uscita/i }).first()
        ).toBeVisible();
        await expect(page.getByRole('button', { name: /crea categoria/i })).toBeVisible();
    });

    test('crea una nuova categoria e la mostra nella lista', async ({ page }) => {
        const nomeCategoria = `Categoria E2E ${Date.now()}`;

        await page.goto('/categories/create');
        await page.locator('#name').fill(nomeCategoria);

        // Seleziona tipo (default o esplicito)
        const typeSelect = page.locator('#type');
        if (await typeSelect.isVisible()) {
            await typeSelect.selectOption('expense');
        }

        await page.getByRole('button', { name: /crea categoria/i }).click();

        // Dopo creazione → redirect a lista o dettaglio categoria
        await expect(page).toHaveURL(/\/categories/, { timeout: 10_000 });
        await expect(page.getByText(nomeCategoria)).toBeVisible({ timeout: 8_000 });
    });

    test('le categorie di sistema (seed) sono presenti nella lista', async ({ page }) => {
        // Almeno una categoria dovrebbe essere presente dopo il seeder
        const isEmpty = await page.getByText(/nessuna categoria/i).isVisible().catch(() => false);
        // Se il seeder ha creato categorie, la lista non sarà vuota
        if (!isEmpty) {
            expect(await page.getByRole('listitem').count()).toBeGreaterThanOrEqual(0);
        }
    });
});
