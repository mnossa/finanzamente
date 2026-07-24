import { test, expect } from '@playwright/test';

test.describe('Widget a formula — tabella', () => {
    test('recipe Tabella apre filtri e modalità lista', async ({ page }) => {
        await page.goto('/widget-formule/crea');
        await expect(page).toHaveURL(/\/widget-formule\/crea/);

        await page.getByRole('button', { name: 'Tabella / lista' }).click();
        await page.getByRole('button', { name: 'Continua' }).click();

        await expect(page.getByRole('heading', { name: 'Scegli metrica e periodo' })).toBeVisible();
        await expect(page.getByText('Filtra movimenti (opzionale)')).toBeVisible();

        await page.getByRole('button', { name: 'Speso nel periodo' }).click();
        await page.getByRole('button', { name: 'Mese corrente', exact: true }).click();
        await page.getByRole('button', { name: 'Continua' }).click();

        await expect(page.getByRole('heading', { name: 'Nome e visualizzazione' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Tabella / lista', exact: true }).or(
            page.getByRole('button', { name: /^Tabella$/ }),
        ).first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'Lista righe' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Aggregata' })).toBeVisible();
    });
});
