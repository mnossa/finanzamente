import { test, expect } from '@playwright/test';

async function selectOptionContainingText(
    page: import('@playwright/test').Page,
    selectId: string,
    textPattern: RegExp
): Promise<void> {
    const select = page.locator(selectId);
    const options = select.locator('option');
    await expect(select).toBeVisible();
    await expect.poll(async () => options.count(), { timeout: 10_000 }).toBeGreaterThan(1);

    const count = await options.count();

    for (let i = 0; i < count; i++) {
        const option = options.nth(i);
        const text = (await option.textContent()) ?? '';
        if (textPattern.test(text)) {
            const value = await option.getAttribute('value');
            if (value) {
                await select.selectOption(value);
                return;
            }
        }
    }

    // Fallback robusto: prima opzione non vuota
    for (let i = 0; i < count; i++) {
        const option = options.nth(i);
        const value = await option.getAttribute('value');
        if (value) {
            await select.selectOption(value);
            return;
        }
    }

    throw new Error(`Opzione selezionabile non trovata per ${selectId}`);
}

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

        await selectOptionContainingText(page, '#source_account_id', /Conto E2E Principale/i);
        await selectOptionContainingText(page, '#dest_household_id', /Casa E2E Secondaria/i);
        await expect(page.locator('#dest_account_id')).toBeEnabled({ timeout: 10_000 });
        await selectOptionContainingText(page, '#dest_account_id', /Conto E2E Secondario/i);
        await page.locator('#source_amount').fill('25');
        await page.locator('#description').fill(descrizione);

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
