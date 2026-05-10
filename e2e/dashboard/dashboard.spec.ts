import { test, expect } from '@playwright/test';

/**
 * Test E2E — Dashboard
 *
 * Verifica che la dashboard carichi e che la navigazione principale funzioni.
 * Non si testa il testo dei link ma la presenza di href semantici e il comportamento.
 */
test.describe('Dashboard principale', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL('/dashboard');
    });

    test('la dashboard si carica correttamente', async ({ page }) => {
        await expect(page).toHaveTitle(/Dashboard/i);
    });

    test('la navigazione principale è presente', async ({ page }) => {
        await expect(page.getByRole('navigation')).toBeVisible();
    });

    test('la navigazione ha un link alla dashboard', async ({ page }) => {
        await expect(
            page.getByRole('navigation').locator('a[href*="/dashboard"]').first()
        ).toBeVisible();
    });

    test('la navigazione ha un link ai conti', async ({ page }) => {
        await expect(
            page.getByRole('navigation').locator('a[href*="/conti"]').first()
        ).toBeVisible();
    });

    test('la navigazione ha un link alle transazioni', async ({ page }) => {
        await expect(
            page.getByRole('navigation').locator('a[href*="/transazioni"]').first()
        ).toBeVisible();
    });

    test('la navigazione ha un link alle categorie', async ({ page }) => {
        await expect(
            page.getByRole('navigation').locator('a[href*="/categorie"]').first()
        ).toBeVisible();
    });

    test('il menu utente apre e naviga al profilo', async ({ page }) => {
        await page.locator('[aria-label*="Menu utente"], [aria-label*="menu utente"]').click();
        await page.locator('a[href*="/profilo"]').first().click();
        await expect(page).toHaveURL('/profilo');
    });

    test('il widget obiettivi mostra i dati del seeder', async ({ page }) => {
        // Dati del seeder: "Obiettivo E2E Vacanza" — verificare che il dato esista
        await expect(page.getByText('Obiettivo E2E Vacanza')).toBeVisible();
    });

    test('il link "vedi tutti" del widget obiettivi porta alla pagina obiettivi', async ({ page }) => {
        await page.locator('a[href*="obiettivi-finanziari"]', { hasText: /vedi tutti/i }).click();
        await expect(page).toHaveURL('/obiettivi-finanziari');
    });
});
