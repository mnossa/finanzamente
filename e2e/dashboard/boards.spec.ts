import { test, expect } from '@playwright/test';

/**
 * WFI-114 — Home e board custom editabili.
 */
test.describe('Le mie dashboard', () => {
    test('Personalizza Home apre modalità edit', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto('/dashboard');
        await page.getByRole('navigation', { name: /navigazione principale/i }).getByRole('link', { name: 'Le mie dashboard' }).click();
        await expect(page.getByRole('heading', { name: 'Le mie dashboard' })).toBeVisible();

        await page.getByTestId('board-customize-home').click();
        await expect(page).toHaveURL(/edit=1/);
        await expect(page.getByRole('region', { name: /modalità personalizzazione/i })).toBeVisible();
        await page.getByTestId('dashboard-add-widget').click();
        await expect(page.getByRole('heading', { name: 'Aggiungi widget' })).toBeVisible();
        await page.getByRole('button', { name: /Proiezione PAC/i }).click();
        await expect(page.getByRole('heading', { name: 'Aggiungi widget' })).toHaveCount(0);
        await expect(page.getByText('Proiezione PAC').first()).toBeVisible();
    });

    test('in personalizzazione dashboard il FAB nuova transazione scompare', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/dashboard/boards');
        await page.getByTestId('board-customize-home').click();
        await expect(page).toHaveURL(/edit=1/);
        await expect(page.getByRole('region', { name: /modalità personalizzazione/i })).toBeVisible();
        await expect(page.getByTestId('mobile-primary-fab')).toHaveCount(0);
    });

    test('Personalizza apre board custom direttamente in modalità edit', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard/);

        await expect(page.getByTestId('dashboard-customize-fab')).toHaveCount(0);
        await expect(page.getByRole('region', { name: /modalità personalizzazione/i })).toHaveCount(0);

        await page.goto('/dashboard/boards');
        await expect(page.getByRole('heading', { name: 'Le mie dashboard' })).toBeVisible();

        const createBtn = page.getByRole('button', { name: 'Nuova dashboard' }).first();
        await createBtn.click();
        await page.getByLabel('Nome').fill(`E2E Board ${Date.now()}`);
        await page.getByLabel('Template iniziale').selectOption('empty');
        await page.getByRole('button', { name: 'Crea' }).click();

        await expect(page.getByText(/Dashboard creata|E2E Board/i).first()).toBeVisible({ timeout: 10000 });

        const customize = page.locator('[data-testid^="board-customize-"]:not([data-testid="board-customize-home"])').first();
        await expect(customize).toBeVisible();
        await customize.click();

        await expect(page).toHaveURL(/board=\d+/);
        await expect(page).toHaveURL(/edit=1/);
        await expect(page.getByRole('region', { name: /modalità personalizzazione/i })).toBeVisible();
        await expect(page.getByTestId('dashboard-customize-fab')).toHaveCount(0);
        await expect(page.getByTestId('dashboard-board-switcher')).toHaveCount(0);
    });
});
