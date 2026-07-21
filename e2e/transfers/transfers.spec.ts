import { test, expect } from '@playwright/test';
import { mobileFabLinkLocator } from '../helpers';

test.describe('Trasferimenti', () => {
    test('la lista trasferimenti è in Movimenti', async ({ page }) => {
        await page.goto('/trasferimenti');
        await expect(page).toHaveURL('/trasferimenti');
        await expect(page).toHaveTitle(/trasferimenti/i);
        const hub = page.getByRole('navigation', { name: 'Movimenti' });
        await expect(hub).toBeVisible();
        await expect(hub.getByRole('link', { name: /trasferim/i })).toHaveAttribute('aria-current', 'page');
    });

    test('da nuova transazione si aprono trasferimento, rimborso e ricorrenza', async ({ page }) => {
        await page.goto('/transazioni/crea');

        const transferEntry = page.getByRole('link', { name: /trasferimento/i })
            .or(page.getByRole('button', { name: /trasferimento/i }));
        await expect(transferEntry.first()).toBeVisible({ timeout: 10_000 });

        await expect(
            page.getByRole('link', { name: /rimborso/i }).or(page.getByRole('button', { name: /rimborso/i })).first(),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: /ricorrenza/i }).or(page.getByRole('button', { name: /ricorrenza/i })).first(),
        ).toBeVisible();

        await transferEntry.first().click();
        await expect(page).toHaveURL(/\/trasferimenti\/crea/);

        await page.goto('/transazioni/crea');
        await page.getByRole('link', { name: /rimborso/i }).or(page.getByRole('button', { name: /rimborso/i })).first().click();
        await expect(page).toHaveURL(/\/rimborsi\/crea/);

        await page.goto('/transazioni/crea');
        await page.getByRole('link', { name: /ricorrenza/i }).or(page.getByRole('button', { name: /ricorrenza/i })).first().click();
        await expect(page).toHaveURL(/\/transazioni-ricorrenti\/crea/);
    });

    test('su mobile il FAB punta alla creazione transazione', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/transazioni');
        await expect(mobileFabLinkLocator(page)).toHaveAttribute('href', /\/transazioni\/crea/);
    });
});
