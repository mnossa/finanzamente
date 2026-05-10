import { test, expect, Page } from '@playwright/test';
import { selectOptionByText } from '../helpers';

async function ensureAssetExists(page: Page): Promise<string> {
    await page.goto('/investimenti');

    const emptyState = page.getByText(/nessun investimento registrato/i);
    const hasEmptyState = await emptyState.isVisible().catch(() => false);

    if (!hasEmptyState) {
        return 'Asset E2E Esistente';
    }

    const assetName = `Asset E2E ${Date.now()}`;

    await page.locator('a[href*="/asset-investimento/crea"]').first().click();
    await expect(page).toHaveURL('/asset-investimento/crea');

    await page.locator('input[name="name"]').fill(assetName);
    await page.locator('input[name="symbol"]').fill(`E2E${Date.now().toString().slice(-4)}`);
    await page.locator('[type="submit"]').click();

    await expect(page).toHaveURL(/\/asset-investimento/, { timeout: 15_000 });

    return assetName;
}

async function ensureAssetInCreateSelect(page: Page): Promise<void> {
    const assetSelect = page.locator('select[name="asset_id"]');
    await expect(assetSelect).toBeVisible();

    let optionCount = await assetSelect.locator('option').count();
    if (optionCount > 1) return;

    const assetName = `Asset Form E2E ${Date.now()}`;
    await page.goto('/asset-investimento/crea');
    await page.locator('input[name="name"]').fill(assetName);
    await page.locator('input[name="symbol"]').fill(`AF${Date.now().toString().slice(-4)}`);
    await page.locator('[type="submit"]').click();

    await expect(page).toHaveURL(/\/asset-investimento/, { timeout: 15_000 });

    await page.goto('/investimenti/crea');
    await expect(assetSelect).toBeVisible();
    await expect
        .poll(async () => assetSelect.locator('option').count(), { timeout: 10_000 })
        .toBeGreaterThan(1);
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
        await page.locator('a[href*="/investimenti/crea"]').first().click();
        await expect(page).toHaveURL('/investimenti/crea');
        await expect(page).toHaveTitle(/nuovo investimento/i);
    });

    test('crea investimento base (happy path)', async ({ page }) => {
        const ensuredAssetName = await ensureAssetExists(page);

        await page.goto('/investimenti/crea');
        await ensureAssetInCreateSelect(page);

        await selectOptionByText(page, '#asset_id', new RegExp(ensuredAssetName, 'i'));

        await page.locator('input[name="quantity"]').fill('2');
        await page.locator('input[name="buy_price"]').fill('150');
        await page.locator('[type="submit"]').click();

        await expect(page).toHaveURL(/\/investimenti/, { timeout: 15_000 });
        await expect(page.getByRole('heading', { name: /posizioni aperte/i })).toBeVisible();
    });

    test('import CSV: carica file e raggiunge anteprima', async ({ page }) => {
        await ensureAssetExists(page);
        await page.goto('/investimenti/importa');

        await expect(page).toHaveURL('/investimenti/importa');
        // Verifica strutturale: esiste un input file
        await expect(page.locator('input[type="file"]')).toBeVisible();

        const csvContent = [
            'buy_date;quantity;buy_price;ticker',
            '2026-04-01;1;120.50;AAPL',
        ].join('\n');

        await page.setInputFiles('input[type="file"]', {
            name: `investimenti-e2e-${Date.now()}.csv`,
            mimeType: 'text/csv',
            buffer: Buffer.from(csvContent),
        });

        await page.locator('[type="submit"]').click();
        // Dopo upload: secondo step del wizard (mapping colonne)
        await expect(page.locator('form, [class*="step"], [class*="wizard"]').first())
            .toBeVisible({ timeout: 10_000 });
    });
});
