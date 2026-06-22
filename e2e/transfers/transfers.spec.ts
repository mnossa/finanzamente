import { test, expect } from '@playwright/test';
import { visibleHrefLocator } from '../helpers';

/**
 * Test E2E — Trasferimenti tra conti
 */
test.describe('Trasferimenti', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/trasferimenti');
    });

    test('la pagina trasferimenti si carica', async ({ page }) => {
        await expect(page).toHaveURL('/trasferimenti');
        await expect(page).toHaveTitle(/trasferimenti/i);
    });

    test('esiste il link per creare un nuovo trasferimento', async ({ page }) => {
        await expect(visibleHrefLocator(page, '/trasferimenti/crea')).toBeVisible();
    });

    test('il link nuovo trasferimento porta al form', async ({ page }) => {
        const createLink = visibleHrefLocator(page, '/trasferimenti/crea');
        await createLink.scrollIntoViewIfNeeded();
        await createLink.click();
        await expect(page).toHaveURL('/trasferimenti/crea');
        await expect(page).toHaveTitle(/nuovo trasferimento/i);
    });

    test('su mobile il CTA è nel corpo pagina', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/trasferimenti');
        const createLink = visibleHrefLocator(page, '/trasferimenti/crea');
        await expect(createLink).toBeVisible();
    });

    test('la lista mostra stato vuoto o righe', async ({ page }) => {
        const emptyState = page.getByRole('heading', { name: /nessun trasferimento/i }).first();
        const rowLinks = page.locator('main a[href*="/trasferimenti/"]:not([href*="/crea"])');

        await expect
            .poll(async () => {
                const emptyVisible = await emptyState.isVisible().catch(() => false);
                const rowsCount = await rowLinks.count();
                return emptyVisible || rowsCount > 0;
            }, { timeout: 15_000 })
            .toBeTruthy();
    });
});
