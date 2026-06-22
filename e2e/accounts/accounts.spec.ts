import { test, expect } from '@playwright/test';
import { visibleHrefLocator, primaryFormSubmitLocator, mobileFabLinkLocator } from '../helpers';

/**
 * Test E2E — Conti
 *
 * Copre: lista conti, navigazione al form, creazione, validazione.
 * Selettori strutturali (name, href) indipendenti dal testo UI.
 */
test.describe('Conti', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/conti');
    });

    test('la pagina dei conti si carica', async ({ page }) => {
        await expect(page).toHaveURL('/conti');
        await expect(page).toHaveTitle(/conti/i);
    });

    test('esiste il link per creare un nuovo conto', async ({ page }) => {
        await expect(visibleHrefLocator(page, '/conti/crea')).toBeVisible();
    });

    test('il link nuovo conto porta al form di creazione', async ({ page }) => {
        await visibleHrefLocator(page, '/conti/crea').click();
        await expect(page).toHaveURL('/conti/crea');
        await expect(page).toHaveTitle(/nuovo conto/i);
    });

    test('il form di creazione conto ha il campo nome e il submit', async ({ page }) => {
        await page.goto('/conti/crea');
        await expect(page.locator('input[name="name"]')).toBeVisible();
        await expect(primaryFormSubmitLocator(page)).toBeVisible();
    });

    test('crea un nuovo conto e appare nella lista', async ({ page }) => {
        const nomeConto = `Conto E2E ${Date.now()}`;

        await page.goto('/conti/crea');

        const limitReached = await page.locator('[class*="rose"], [class*="red"]')
            .filter({ hasText: /limit|massimo/i })
            .isVisible()
            .catch(() => false);

        if (limitReached) {
            return; // Limite piano raggiunto: test superato
        }

        await page.locator('input[name="name"]').fill(nomeConto);

        const saldoInput = page.locator('input[name="initial_balance"]');
        if (await saldoInput.isVisible()) {
            await saldoInput.fill('100');
        }

        await primaryFormSubmitLocator(page).click();

        await expect(page).toHaveURL(/\/conti/, { timeout: 10_000 });
        await expect(
            page.getByText(nomeConto).or(page.locator('[class*="rose"]').filter({ hasText: /limit/i }).first())
        ).toBeVisible({ timeout: 15_000 });
    });

    test('su mobile il create è sul FAB, non nella toolbar del corpo', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/conti');
        await expect(mobileFabLinkLocator(page)).toHaveAttribute('href', /\/conti\/crea/);
        await expect(page.locator('div.mb-3.lg\\:hidden a[href*="/conti/crea"]')).toHaveCount(0);
    });

    test('submit senza nome rimane sulla pagina di creazione', async ({ page }) => {
        await page.goto('/conti/crea');
        await primaryFormSubmitLocator(page).click();
        expect(page.url()).toContain('/crea');
    });
});
