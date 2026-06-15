import { test, expect, type Page } from '@playwright/test';
import { visibleHrefLocator, primaryFormSubmitLocator } from '../helpers';

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
        await expect(visibleHrefLocator(page, '/obiettivi-finanziari/crea')).toBeVisible();
    });

    test('crea un nuovo obiettivo e appare nella lista', async ({ page }) => {
        const name = `Obiettivo E2E ${Date.now()}`;

        await visibleHrefLocator(page, '/obiettivi-finanziari/crea').click();
        await expect(page).toHaveURL('/obiettivi-finanziari/crea');

        await page.locator('input[name="name"]').fill(name);
        await page.locator('input[name="target_amount"]').fill('1000');

        await primaryFormSubmitLocator(page).click();

        await expect(page).toHaveURL(/obiettivi-finanziari/, { timeout: 10_000 });
        await expect(
            page.getByText(name).or(page.locator('[class*="amber"], [class*="rose"]').filter({ hasText: /limit/i }).first())
        ).toBeVisible({ timeout: 8_000 });
    });

    test('il dettaglio di un obiettivo si apre correttamente', async ({ page }) => {
        await page.getByRole('link', { name: 'Obiettivo E2E Vacanza' }).filter({ visible: true }).first().click();
        await expect(page).toHaveURL(/obiettivi-finanziari\/\d+/);
        await expect(page).toHaveTitle(/obiettiv/i);
    });
});

test.describe('Widget Obiettivi in Dashboard', () => {
    /** Pannello widget (header + body): h3 → flex row → colonna titolo → header → shell. */
    function goalsWidget(page: Page) {
        return page.getByRole('heading', { name: 'Obiettivi finanziari' }).locator('../../../..');
    }

    function goalDetailLinks(page: Page) {
        return goalsWidget(page).locator('a[href*="/obiettivi-finanziari/"]');
    }

    test.beforeEach(async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL('/dashboard');
    });

    test('il widget ha un link alla pagina obiettivi', async ({ page }) => {
        const widget = goalsWidget(page);
        await widget.scrollIntoViewIfNeeded();
        await expect(widget.getByRole('link', { name: /vedi tutti/i })).toBeVisible();
    });

    test('il widget mostra almeno un obiettivo attivo', async ({ page }) => {
        const widget = goalsWidget(page);
        await widget.scrollIntoViewIfNeeded();
        await expect(goalDetailLinks(page).first()).toBeVisible();
    });

    test('il widget mostra la percentuale di avanzamento', async ({ page }) => {
        const widget = goalsWidget(page);
        await widget.scrollIntoViewIfNeeded();
        await expect(goalDetailLinks(page).first()).toContainText(/%/);
    });

    test('il link "vedi tutti" porta alla pagina obiettivi', async ({ page }) => {
        const widget = goalsWidget(page);
        await widget.scrollIntoViewIfNeeded();
        await widget.getByRole('link', { name: /vedi tutti/i }).click();
        await expect(page).toHaveURL('/obiettivi-finanziari');
    });

    test('cliccando un obiettivo nel widget si apre il dettaglio', async ({ page }) => {
        const widget = goalsWidget(page);
        await widget.scrollIntoViewIfNeeded();
        await goalDetailLinks(page).first().click();
        await expect(page).toHaveURL(/obiettivi-finanziari\/\d+/);
    });
});
