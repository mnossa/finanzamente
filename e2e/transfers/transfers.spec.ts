import { test, expect } from '@playwright/test';
import { mobileFabLinkLocator } from '../helpers';

test.describe('Trasferimenti', () => {
    test('la route trasferimenti reindirizza a transazioni', async ({ page }) => {
        await page.goto('/trasferimenti');
        await expect(page).toHaveURL('/transazioni');
        await expect(page.getByText('I trasferimenti ora sono gestiti da Conti e movimenti.')).toBeVisible();
    });

    test('su mobile il FAB punta alla creazione transazione', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/transazioni');
        await expect(mobileFabLinkLocator(page)).toHaveAttribute('href', /\/transazioni\/crea/);
    });
});
