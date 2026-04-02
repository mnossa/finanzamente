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

        await page.getByLabel(/nome/i).fill(name);
        await page.getByLabel(/importo.*target|target.*importo|obiettivo/i).fill('1000');

        await page.getByRole('button', { name: /salva|crea|conferma/i }).click();

        await expect(page).toHaveURL(/obiettivi-finanziari/);
        await expect(page.getByText(name)).toBeVisible();
    });

    test('il dettaglio di un obiettivo si apre correttamente', async ({ page }) => {
        await page.getByText('Obiettivo E2E Vacanza').click();
        await expect(page).toHaveURL(/obiettivi-finanziari\/\d+/);
        await expect(page.getByText('Obiettivo E2E Vacanza')).toBeVisible();
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
        const widget = page.locator('div', { has: page.getByText('Obiettivi Finanziari') }).first();
        await widget.getByRole('link', { name: /vedi tutti/i }).click();
        await expect(page).toHaveURL('/obiettivi-finanziari');
    });

    test('cliccando un obiettivo nel widget si apre il dettaglio', async ({ page }) => {
        const goalLink = page.getByRole('link', { name: /obiettivo e2e vacanza/i });
        await expect(goalLink).toBeVisible();
        await goalLink.click();
        await expect(page).toHaveURL(/obiettivi-finanziari\/\d+/);
    });
});
