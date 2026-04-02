import { test, expect } from '@playwright/test';

/**
 * Test E2E — Dashboard
 *
 * Verifica che la dashboard principale carichi correttamente
 * dopo l'autenticazione e contenga gli elementi fondamentali.
 */
test.describe('Dashboard principale', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL('/dashboard');
    });

    test('carica la dashboard con titolo corretto', async ({ page }) => {
        await expect(page).toHaveTitle(/Dashboard/i);
    });

    test('mostra la navigazione laterale/superiore', async ({ page }) => {
        await expect(page.getByRole('navigation')).toBeVisible();
    });

    test('la navigazione contiene il link alla Dashboard', async ({ page }) => {
        await expect(
            page.getByRole('link', { name: /^dashboard$/i })
        ).toBeVisible();
    });

    test('la navigazione contiene il link ai Conti', async ({ page }) => {
        await expect(
            page.getByRole('link', { name: /^conti$/i })
        ).toBeVisible();
    });

    test('la navigazione contiene il link alle Transazioni', async ({ page }) => {
        await expect(
            page.getByRole('link', { name: /^transazioni$/i })
        ).toBeVisible();
    });

    test('la navigazione contiene il link alle Categorie', async ({ page }) => {
        await expect(
            page.getByRole('link', { name: /^categorie$/i })
        ).toBeVisible();
    });

    test('il link al profilo nel dropdown funziona', async ({ page }) => {
        // Apri il menu utente tramite aria-label (funziona su desktop e mobile)
        const userMenu = page.getByRole('button', { name: /menu utente/i });
        await userMenu.click();

        await page.getByRole('link', { name: /^profilo$/i }).click();
        await expect(page).toHaveURL('/profilo');
    });

    test('il widget Obiettivi Finanziari mostra gli obiettivi del seeder', async ({ page }) => {
        // Il seeder E2E crea un obiettivo "Obiettivo E2E Vacanza"
        await expect(page.getByText('Obiettivi Finanziari')).toBeVisible();
        await expect(page.getByText('Obiettivo E2E Vacanza')).toBeVisible();
    });

    test('il widget Obiettivi Finanziari ha un link a Vedi tutti', async ({ page }) => {
        const link = page.getByRole('link', { name: /vedi tutti/i }).first();
        await expect(link).toBeVisible();
    });

    test('il link Vedi tutti del widget obiettivi porta alla pagina degli obiettivi', async ({ page }) => {
        // Clicca sul link "Vedi tutti" dentro il widget obiettivi
        await page.getByText('Obiettivi Finanziari').waitFor();
        await page.locator('a[href*="obiettivi-finanziari"]', { hasText: /vedi tutti/i }).click();
        await expect(page).toHaveURL('/obiettivi-finanziari');
    });
});
