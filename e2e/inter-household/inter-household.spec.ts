import { test, expect } from '@playwright/test';
import { selectOptionByText } from '../helpers';

test.describe('Trasferimenti Inter-Household', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/trasferimenti-tra-nuclei');
    });

    test('carica pagina elenco trasferimenti', async ({ page }) => {
        await expect(page).toHaveURL('/trasferimenti-tra-nuclei');
        await expect(page).toHaveTitle(/trasferimenti tra households/i);
    });

    test('apre form creazione trasferimento', async ({ page }) => {
        await page.getByRole('link', { name: /nuovo trasferimento/i }).first().click();
        await expect(page).toHaveURL('/trasferimenti-tra-nuclei/crea');
        await expect(page).toHaveTitle(/nuovo trasferimento tra households/i);
    });

    test('crea trasferimento, verifica in lista e apre dettaglio', async ({ page }) => {
        const descrizione = `Interhousehold E2E ${Date.now()}`;

        await page.goto('/trasferimenti-tra-nuclei/crea');

        await selectOptionByText(page, '#source_account_id', /Conto E2E Principale/i);
        await selectOptionByText(page, '#dest_household_id', /Casa E2E Secondaria/i);
        await expect(page.locator('select[name="dest_account_id"]')).toBeEnabled({ timeout: 10_000 });
        await selectOptionByText(page, '#dest_account_id', /Conto E2E Secondario/i);
        await page.locator('input[name="source_amount"]').fill('25');
        await page.locator('input[name="description"], textarea[name="description"]').first().fill(descrizione);

        await page.getByRole('button', { name: /crea trasferimento/i }).click();

        await expect(page).toHaveURL(/\/trasferimenti-tra-nuclei\/\d+/, { timeout: 15_000 });
        await expect(page.getByRole('heading', { name: /informazioni trasferimento/i })).toBeVisible();
        await expect(page.getByText(descrizione)).toBeVisible();

        await page.getByRole('link', { name: /torna alla lista/i }).click();
        await expect(page).toHaveURL('/trasferimenti-tra-nuclei');
        await expect(page.getByText(descrizione)).toBeVisible({ timeout: 10_000 });

        await page.getByText(descrizione).click();
        await expect(page).toHaveURL(/\/trasferimenti-tra-nuclei\/\d+/, { timeout: 10_000 });
        await expect(page.getByText(descrizione)).toBeVisible();
    });
});
