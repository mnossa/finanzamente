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

    test('anteprima tabella non overflowa orizzontalmente su mobile', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'autenticato-mobile', 'Overflow layout rilevante su viewport mobile');

        await page.goto('/widget-formule/crea');
        await page.getByRole('button', { name: 'Tabella / lista' }).click();
        await page.getByRole('button', { name: 'Continua' }).click();

        await expect(page.getByRole('heading', { name: 'Anteprima', exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Mese corrente', exact: true }).click();

        const preview = page.locator('div').filter({
            has: page.getByRole('heading', { name: 'Anteprima', exact: true }),
        }).last();

        await expect(preview.getByRole('heading', { name: 'Elenco movimenti' }).or(
            preview.getByText('Nessun dato per i filtri selezionati.'),
        ).first()).toBeVisible({ timeout: 15000 });

        // Tabella presente (con o senza righe): le colonne secondarie non devono spingere la pagina.
        const table = preview.locator('table').first();
        if (await table.count()) {
            await expect(table).toBeVisible();
            await expect(preview.getByRole('columnheader', { name: 'Data' })).toBeVisible();
            await expect(preview.getByRole('columnheader', { name: 'Descrizione' })).toBeVisible();
            await expect(preview.getByRole('columnheader', { name: /Importo/i })).toBeVisible();
            // Categoria/Conto restano sm+ only
            await expect(preview.getByRole('columnheader', { name: 'Categoria' })).toHaveCount(0);
            await expect(preview.getByRole('columnheader', { name: 'Conto' })).toHaveCount(0);
        }

        const { scrollWidth, clientWidth } = await page.evaluate(() => ({
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
        }));
        expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
    });
});
