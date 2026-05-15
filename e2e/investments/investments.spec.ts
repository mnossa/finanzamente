import { test, expect, Page } from '@playwright/test';
import { selectOptionByText, visibleHrefLocator, primaryFormSubmitLocator } from '../helpers';

/** Dopo store asset: solo index, non /crea (il regex largo mascherava errori di validazione). */
const assetIndexUrl = /\/asset-investimento\/?(\?.*)?$/;

async function createInvestmentAsset(page: Page, assetName: string): Promise<void> {
    await page.goto('/asset-investimento/crea');
    await page.locator('input[name="name"]').fill(assetName);
    await page.locator('input[name="symbol"]').fill(`E2E${Date.now().toString().slice(-6)}`);
    await primaryFormSubmitLocator(page).click();
    await expect(page).toHaveURL(assetIndexUrl, { timeout: 15_000 });
}

async function ensureAssetExists(page: Page): Promise<string> {
    await page.goto('/investimenti/crea');
    const assetSelect = page.locator('select[name="asset_id"]');
    await expect(assetSelect).toBeVisible();

    if ((await assetSelect.locator('option').count()) > 1) {
        const label = (await assetSelect.locator('option[value]:not([value=""])').first().textContent()) ?? '';
        return label.trim();
    }

    const assetName = `Asset E2E ${Date.now()}`;
    await createInvestmentAsset(page, assetName);
    return assetName;
}

/** Garantisce almeno un asset nella select del form investimento; ritorna testo opzione da selezionare. */
async function ensureAssetInCreateSelect(page: Page): Promise<string> {
    await page.goto('/investimenti/crea');
    const assetSelect = page.locator('select[name="asset_id"]');
    await expect(assetSelect).toBeVisible();

    let optionCount = await assetSelect.locator('option').count();
    if (optionCount > 1) {
        return ((await assetSelect.locator('option[value]:not([value=""])').first().textContent()) ?? '').trim();
    }

    const assetName = `Asset Form E2E ${Date.now()}`;
    await createInvestmentAsset(page, assetName);

    await page.goto('/investimenti/crea');
    await expect(assetSelect).toBeVisible();
    await expect
        .poll(async () => assetSelect.locator('option').count(), { timeout: 15_000 })
        .toBeGreaterThan(1);

    return assetName;
}

test.describe('Investimenti', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/investimenti');
    });

    test('la pagina investimenti si carica', async ({ page }) => {
        await expect(page).toHaveURL('/investimenti');
        await expect(page).toHaveTitle(/investimenti/i);
    });

    test('naviga al form nuovo investimento', async ({ page }) => {
        await visibleHrefLocator(page, '/investimenti/crea').click();
        await expect(page).toHaveURL('/investimenti/crea');
        await expect(page).toHaveTitle(/nuovo investimento/i);
    });

    test('crea investimento base (happy path)', async ({ page }) => {
        await ensureAssetInCreateSelect(page);

        await selectOptionByText(page, '#asset_id', /Asset E2E|E2ESEED/i);

        await page.locator('input[name="quantity"]').fill('2');
        await page.locator('input[name="buy_price"]').fill('150');
        await primaryFormSubmitLocator(page).click();

        await expect(page).toHaveURL(/\/investimenti/, { timeout: 15_000 });
        await expect(page.getByRole('heading', { name: /posizioni aperte/i })).toBeVisible();
    });

    test('import CSV: carica file e raggiunge anteprima', async ({ page }) => {
        await ensureAssetExists(page);
        await page.goto('/investimenti/importa');

        await expect(page).toHaveURL('/investimenti/importa');
        // Input file nascosto (stile custom): presente nel DOM, setInputFiles funziona comunque
        await expect(page.locator('input[type="file"]')).toHaveCount(1);

        const csvContent = [
            'buy_date;quantity;buy_price;ticker',
            '2026-04-01;1;120.50;AAPL',
        ].join('\n');

        await page.setInputFiles('input[type="file"]', {
            name: `investimenti-e2e-${Date.now()}.csv`,
            mimeType: 'text/csv',
            buffer: Buffer.from(csvContent),
        });

        await page.getByRole('button', { name: /avanti/i }).click();
        await expect(page.getByRole('heading', { name: /Mappatura colonne/i })).toBeVisible({ timeout: 15_000 });
    });

    test('su mobile dalla lista investimenti il link Importa CSV è nella toolbar sotto header', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/investimenti');
        const importLink = visibleHrefLocator(page, '/investimenti/importa');
        await expect(importLink).toBeVisible();
        await importLink.click();
        await expect(page).toHaveURL(/\/investimenti\/importa/);
    });
});
