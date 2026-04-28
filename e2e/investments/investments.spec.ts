import { test, expect, Page } from '@playwright/test';

async function ensureAssetExists(page: Page): Promise<string> {
    await page.goto('/investimenti');

    const emptyState = page.getByText(/nessun investimento registrato/i);
    const hasEmptyState = await emptyState.isVisible().catch(() => false);

    if (!hasEmptyState) {
        return 'Asset E2E Esistente';
    }

    const assetName = `Asset E2E ${Date.now()}`;

    await page.getByRole('link', { name: /crea asset/i }).click();
    await expect(page).toHaveURL('/asset-investimento/crea');

    await page.locator('#name').fill(assetName);
    await page.locator('#symbol').fill(`E2E${Date.now().toString().slice(-4)}`);
    await page.getByRole('button', { name: /crea asset/i }).click();

    await expect(page).toHaveURL(/\/asset-investimento/, { timeout: 15_000 });

    return assetName;
}

async function ensureAssetInCreateSelect(page: Page): Promise<void> {
    const assetSelect = page.locator('#asset_id');
    await expect(assetSelect).toBeVisible();

    let optionCount = await assetSelect.locator('option').count();
    if (optionCount > 1) {
        return;
    }

    const assetName = `Asset Form E2E ${Date.now()}`;
    await page.goto('/asset-investimento/crea');
    await expect(page).toHaveURL('/asset-investimento/crea');

    await page.locator('#name').fill(assetName);
    await page.locator('#symbol').fill(`AF${Date.now().toString().slice(-4)}`);
    await page.getByRole('button', { name: /crea asset/i }).click();

    await expect(page).toHaveURL(/\/asset-investimento/, { timeout: 15_000 });

    await page.goto('/investimenti/crea');
    await expect(page.locator('#asset_id')).toBeVisible();
    await expect
        .poll(async () => page.locator('#asset_id option').count(), { timeout: 10_000 })
        .toBeGreaterThan(1);
    optionCount = await page.locator('#asset_id option').count();
    expect(optionCount).toBeGreaterThan(1);
}

test.describe('Investimenti', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/investimenti');
    });

    test('carica la pagina investimenti', async ({ page }) => {
        await expect(page).toHaveURL('/investimenti');
        await expect(page).toHaveTitle(/investimenti/i);
    });

    test('naviga al form nuovo investimento', async ({ page }) => {
        await page.getByRole('link', { name: /nuovo investimento/i }).first().click();
        await expect(page).toHaveURL('/investimenti/crea');
        await expect(page).toHaveTitle(/nuovo investimento/i);
    });

    test('crea investimento base (happy path)', async ({ page }) => {
        const ensuredAssetName = await ensureAssetExists(page);

        await page.goto('/investimenti/crea');
        await ensureAssetInCreateSelect(page);

        const assetSelect = page.locator('#asset_id');

        const targetOption = assetSelect
            .locator('option')
            .filter({ hasText: new RegExp(ensuredAssetName, 'i') })
            .first();

        const hasTarget = await targetOption.isVisible().catch(() => false);
        if (hasTarget) {
            const value = await targetOption.getAttribute('value');
            if (value) {
                await assetSelect.selectOption(value);
            }
        } else {
            await assetSelect.selectOption({ index: 1 });
        }

        await page.locator('#quantity').fill('2');
        await page.locator('#buy_price').fill('150');
        await page.getByRole('button', { name: /registra investimento/i }).click();

        await expect(page).toHaveURL(/\/investimenti/, { timeout: 15_000 });
        await expect(page.getByRole('heading', { name: /posizioni aperte/i })).toBeVisible();
    });

    test('import base: carica file e raggiunge anteprima', async ({ page }) => {
        await ensureAssetExists(page);
        await page.goto('/investimenti/importa');

        await expect(page).toHaveURL('/investimenti/importa');
        await expect(page.getByText(/carica il file csv/i)).toBeVisible();

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
        await expect(page.getByText(/mappatura colonne/i)).toBeVisible();

        await page.getByRole('button', { name: /anteprima/i }).click();
        await expect(page.getByText(/^totale righe$/i)).toBeVisible({ timeout: 20_000 });
    });
});
