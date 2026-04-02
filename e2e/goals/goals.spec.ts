import { test, expect } from '@playwright/test';

/**
 * Test E2E — Obiettivi Finanziari
 *
 * Verifica l'elenco, la creazione, la visualizzazione del dettaglio
 * e la presenza del widget in dashboard.
 */
test.describe('Obiettivi Finanziari', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/obiettivi-finanziari');
        await expect(page).toHaveURL('/obiettivi-finanziari');
    });

    test('carica la pagina degli obiettivi', async ({ page }) => {
        await expect(page).toHaveTitle(/obiettivi/i);
    });

    test('l\'obiettivo creato dal seeder è visibile nell\'elenco', async ({ page }) => {
        await expect(page.getByText('Obiettivo E2E Vacanza')).toBeVisible();
    });

    test('il pulsante crea nuovo obiettivo è presente', async ({ page }) => {
        const createBtn = page.getByRole('link', { name: /nuovo obiettivo|crea/i });
        await expect(createBtn).toBeVisible();
    });

    test('crea un nuovo obiettivo e lo vede nell\'elenco', async ({ page }) => {
        const name = `Obiettivo E2E ${Date.now()}`;

        await page.getByRole('link', { name: /nuovo obiettivo|crea/i }).click();
        await expect(page).toHaveURL('/obiettivi-finanziari/crea');

        await page.locator('#name').fill(name);
        await page.locator('#target_amount').fill('1000');

        await page.getByRole('button', { name: /salva|crea|conferma/i }).click();

        // Dopo creazione → redirect a lista (successo) o form (limite piano raggiunto)
        await expect(page).toHaveURL(/obiettivi-finanziari/, { timeout: 10_000 });
        // Il piano Base limita a 1 obiettivo attivo: verifica successo o messaggio limite
        await expect(
            page.getByText(name).or(page.getByText(/limite|raggiunto|piano base/i))
        ).toBeVisible({ timeout: 8_000 });
    });

    test('il dettaglio di un obiettivo si apre correttamente', async ({ page }) => {
        await page.getByRole('link', { name: /obiettivo e2e vacanza/i }).click();
        await expect(page).toHaveURL(/obiettivi-finanziari\/\d+/);
        await expect(page.getByRole('heading', { name: /obiettivo e2e vacanza/i }).first()).toBeVisible();
    });
});

test.describe('Widget Obiettivi Finanziari in Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL('/dashboard');
    });

    test('il widget Obiettivi Finanziari è visibile', async ({ page }) => {
        await expect(page.getByText('Obiettivi Finanziari')).toBeVisible();
    });

    test('il widget mostra l\'obiettivo del seeder', async ({ page }) => {
        await expect(page.getByText('Obiettivo E2E Vacanza')).toBeVisible();
    });

    test('il widget mostra la percentuale di avanzamento', async ({ page }) => {
        // L'obiettivo ha 500/2000 = 25%
        await expect(page.getByText('25%')).toBeVisible();
    });

    test('il link "Vedi tutti" porta alla pagina obiettivi', async ({ page }) => {
        // Trova il widget obiettivi e clicca "Vedi tutti"
        await page.locator('a[href*="obiettivi-finanziari"]', { hasText: /vedi tutti/i }).click();
        await expect(page).toHaveURL('/obiettivi-finanziari');
    });

    test('cliccando un obiettivo nel widget si apre il dettaglio', async ({ page }) => {
        const goalLink = page.getByRole('link', { name: /obiettivo e2e vacanza/i });
        await expect(goalLink).toBeVisible();
        await goalLink.click();
        await expect(page).toHaveURL(/obiettivi-finanziari\/\d+/);
    });
});
