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

    test('su mobile mostra azioni rapide compatte e bottom nav', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/dashboard');

        const heading = page.getByRole('heading', { name: 'Azioni rapide' });
        await heading.scrollIntoViewIfNeeded();
        await expect(heading).toBeVisible();
        await expect(page.getByRole('main').getByRole('link', { name: 'Nuova transazione' })).toBeVisible();
        await expect(page.getByRole('navigation', { name: /navigazione rapida/i })).toBeVisible();
    });

    test('la navigazione principale è presente', async ({ page }) => {
        // Almeno una delle due nav (sidebar o bottom nav) deve essere visibile
        const navPrincipale = page.getByRole('navigation', { name: /navigazione principale/i });
        const navRapida = page.getByRole('navigation', { name: /navigazione rapida/i });
        await expect(navPrincipale.or(navRapida).first()).toBeVisible();
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

    function goalsWidget(page: import('@playwright/test').Page) {
        return page.getByRole('heading', { name: 'Obiettivi finanziari' }).locator('../../..');
    }

    test('il widget obiettivi mostra almeno un obiettivo attivo', async ({ page }) => {
        const widget = goalsWidget(page);
        await widget.scrollIntoViewIfNeeded();
        await expect(widget.locator('a[href*="/obiettivi-finanziari/"]').first()).toBeVisible();
    });

    test('il link "vedi tutti" del widget obiettivi porta alla pagina obiettivi', async ({ page }) => {
        const widget = goalsWidget(page);
        await widget.scrollIntoViewIfNeeded();
        await widget.getByRole('link', { name: /vedi tutti/i }).click();
        await expect(page).toHaveURL('/obiettivi-finanziari');
    });
});
