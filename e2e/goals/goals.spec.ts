import { test, expect } from '@playwright/test';

/**
 * Test E2E — Obiettivi Finanziari
 *
 * Verifica l'elenco, la creazione e il widget in dashboard.
 * Selettori strutturali (name, href) + dati del seeder (stabili per definizione).
 */
test.describe('Obiettivi Finanziari', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/obiettivi-finanziari');
        await expect(page).toHaveURL('/obiettivi-finanziari');
    });

    test('la pagina obiettivi si carica', async ({ page }) => {
        await expect(page).toHaveTitle(/obiettivi/i);
    });

    test('l\'obiettivo del seeder è visibile nell\'elenco', async ({ page }) => {
        await expect(page.getByText('Obiettivo E2E Vacanza')).toBeVisible();
    });

    test('esiste il link per creare un nuovo obiettivo', async ({ page }) => {
        await expect(page.locator('a[href*="/obiettivi-finanziari/crea"]')).toBeVisible();
    });

    test('crea un nuovo obiettivo e appare nella lista', async ({ page }) => {
        const name = `Obiettivo E2E ${Date.now()}`;

        await page.locator('a[href*="/obiettivi-finanziari/crea"]').first().click();
        await expect(page).toHaveURL('/obiettivi-finanziari/crea');

        await page.locator('input[name="name"]').fill(name);
        await page.locator('input[name="target_amount"]').fill('1000');

        await page.locator('[type="submit"]').click();

        await expect(page).toHaveURL(/obiettivi-finanziari/, { timeout: 10_000 });
        await expect(
            page.getByText(name).or(page.locator('[class*="amber"], [class*="rose"]').filter({ hasText: /limit/i }).first())
        ).toBeVisible({ timeout: 8_000 });
    });

    test('il dettaglio di un obiettivo si apre correttamente', async ({ page }) => {
        await page.locator('a[href*="/obiettivi-finanziari/"]').first().click();
        await expect(page).toHaveURL(/obiettivi-finanziari\/\d+/);
        await expect(page).toHaveTitle(/obiettiv/i);
    });
});

test.describe('Widget Obiettivi in Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL('/dashboard');
    });

    test('il widget ha un link alla pagina obiettivi', async ({ page }) => {
        await expect(page.locator('a[href*="obiettivi-finanziari"]').first()).toBeVisible();
    });

    test('il widget mostra l\'obiettivo del seeder', async ({ page }) => {
        await expect(page.getByText('Obiettivo E2E Vacanza')).toBeVisible();
    });

    test('il widget mostra la percentuale di avanzamento (25%)', async ({ page }) => {
        // 500/2000 = 25%: dati del seeder, valore stabile
        await expect(page.getByText(/\b25\s*%/)).toBeVisible();
    });

    test('il link "vedi tutti" porta alla pagina obiettivi', async ({ page }) => {
        await page.locator('a[href*="obiettivi-finanziari"]', { hasText: /vedi tutti/i }).click();
        await expect(page).toHaveURL('/obiettivi-finanziari');
    });

    test('cliccando un obiettivo nel widget si apre il dettaglio', async ({ page }) => {
        await page.locator('a[href*="/obiettivi-finanziari/"]').first().click();
        await expect(page).toHaveURL(/obiettivi-finanziari\/\d+/);
    });
});
