import { test, expect } from '@playwright/test';

/**
 * E2E — Widget con query dinamica (tag + esclusione categoria) e formula IF.
 */
test.describe('Formula widgets — metriche dinamiche', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/widget-formule/crea');
        await expect(page).toHaveURL(/\/widget-formule\/crea/);
    });

    test('crea widget conteggio per tag con preset e guida IF/WHEN', async ({ page }) => {
        await page.getByRole('button', { name: 'Conteggio per tag' }).click();
        await expect(page.getByText('Query dinamica (opzionale)')).toBeVisible();
        await expect(page.getByText('Funzioni formula avanzate')).toBeVisible();

        const uniqueName = `Tag count E2E ${Date.now()}`;
        await page.getByLabel('Nome widget').fill(uniqueName);
        await page.getByLabel('Periodo *').selectOption({ label: 'Ultimi 30 giorni' });

        await page.getByRole('button', { name: 'Crea widget' }).click();

        await expect
            .poll(async () => {
                if (/\/widget-formule$/.test(page.url())) {
                    return 'saved';
                }
                if (await page.getByText('Widget già presente').count()) {
                    return 'duplicate';
                }

                return 'pending';
            }, { timeout: 10000 })
            .not.toBe('pending');

        if (await page.getByText('Widget già presente').isVisible()) {
            await page.getByRole('link', { name: 'Vai ai miei widget' }).click();
        }

        await expect(page).toHaveURL(/\/widget-formule$/);
    });

    test('preset spese per tag mostra filtri runtime modificabili in anteprima', async ({ page }) => {
        await page.getByRole('button', { name: 'Spese per tag (escludi categoria)' }).click();
        await page.getByLabel('Periodo *').selectOption({ label: 'Mese corrente' });

        const preview = page.locator('div').filter({
            has: page.getByRole('heading', { name: 'Anteprima', exact: true }),
        }).last();

        const tagSelect = preview.getByLabel('Tag', { exact: true });
        const categorySelect = preview.getByLabel('Escludi Categoria', { exact: true });
        await expect(tagSelect).toBeVisible({ timeout: 15000 });
        await expect(categorySelect).toBeVisible();

        const tagOptions = await tagSelect.locator('option').count();
        expect(tagOptions).toBeGreaterThan(0);
        await tagSelect.selectOption({ index: Math.min(1, tagOptions - 1) });
        await expect(page.getByText('Anteprima non disponibile')).toBeHidden();
    });

    test('scenario IF disponibile nella creazione variabile', async ({ page }) => {
        await page.getByRole('button', { name: '+ Nuova metrica' }).click();
        await expect(page.getByRole('heading', { name: 'Crea variabile personalizzata' })).toBeVisible();

        const dialog = page.getByRole('dialog');
        await dialog.getByRole('button', { name: 'Periodo', exact: true }).click();
        await dialog.getByRole('button', { name: 'Alert spese elevate' }).click();

        await expect(dialog.getByText('IF([period_expenses] > 1000, 1, 0)')).toBeVisible();
    });
});
