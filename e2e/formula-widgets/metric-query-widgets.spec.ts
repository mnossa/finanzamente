import { test, expect, type Page } from '@playwright/test';

async function openCreateStep2(page: Page) {
    await page.goto('/widget-formule/crea');
    await expect(page).toHaveURL(/\/widget-formule\/crea/);
    await page.getByRole('button', { name: 'Continua' }).click();
    await expect(page.getByRole('heading', { name: 'Scegli metrica e periodo' })).toBeVisible();
    await expect(page.getByTestId('metric-scenario-picker')).toBeVisible();

    // Sempre tap esplicito: seed può avere variabile senza evidenziare lo step Excel-like.
    await page.getByRole('button', { name: 'Liquidità attuale' }).click();
    await expect(page.getByText(/Metrica «Liquidità attuale»/)).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('button', { name: 'Liquidità attuale' })).toHaveAttribute('aria-pressed', 'true');
}

async function openFilters(page: Page) {
    await page.getByRole('button', { name: /Filtra movimenti/i }).click();
}

/**
 * E2E — Widget con query dinamica (tag + esclusione categoria) e formula IF.
 */
test.describe('Formula widgets — metriche dinamiche', () => {
    test('crea widget conteggio per tag con preset', async ({ page }) => {
        await openCreateStep2(page);
        await openFilters(page);
        await page.getByRole('button', { name: 'Conteggio per tag' }).click();

        await page.getByRole('button', { name: 'Ultimi 30 giorni', exact: true }).click();
        await expect(page.getByRole('button', { name: 'Continua' })).toBeEnabled();
        await page.getByRole('button', { name: 'Continua' }).click();
        await expect(page.getByRole('heading', { name: 'Nome e visualizzazione' })).toBeVisible();

        const uniqueName = `Tag count E2E ${Date.now()}`;
        await page.getByLabel('Nome widget').fill(uniqueName);
        const createButton = page.getByRole('button', { name: 'Crea widget' });
        await expect(createButton).toBeEnabled({ timeout: 10000 });
        await createButton.click();

        await expect
            .poll(async () => {
                if (/\/widget-formule$/.test(page.url())) {
                    return 'saved';
                }
                if (await page.getByText('Già pronto').count()) {
                    return 'duplicate';
                }

                return 'pending';
            }, { timeout: 10000 })
            .not.toBe('pending');

        if (await page.getByText('Già pronto').isVisible()) {
            await page.getByRole('link', { name: 'Vai ai miei widget' }).click();
        }

        await expect(page).toHaveURL(/\/widget-formule$/);
    });

    test('preset spese per tag mostra filtri runtime modificabili in anteprima', async ({ page }) => {
        await openCreateStep2(page);
        await openFilters(page);
        await page.getByRole('button', { name: 'Spese per tag (escludi categoria)' }).click();
        await page.getByRole('button', { name: 'Mese corrente', exact: true }).click();

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

    test('filtro tipo transazione non runtime mostra il selettore valore', async ({ page }) => {
        await openCreateStep2(page);
        await openFilters(page);
        await expect(page.getByText('Filtra movimenti (opzionale)')).toBeVisible();

        await page.getByRole('button', { name: '+ Aggiungi filtro' }).click();

        const row = page.getByTestId('metric-filter-row').last();
        await expect(row).toBeVisible();

        await row.getByRole('combobox').first().selectOption('transaction_type');
        await row.getByRole('checkbox').uncheck();

        const valueSelect = row.getByRole('combobox').nth(2);
        await expect(valueSelect).toBeVisible();
        await valueSelect.selectOption('expense');
        await expect(valueSelect.locator('option:checked')).toHaveText('Uscite');
    });

    test('scenario alert spese disponibile nella creazione metrica', async ({ page }) => {
        await openCreateStep2(page);
        await page.getByRole('button', { name: /Formula personalizzata/i }).click();
        await expect(page.getByRole('heading', { name: 'Formula personalizzata' })).toBeVisible();

        const dialog = page.getByRole('dialog');
        await dialog.getByRole('button', { name: 'Esplora scenari' }).click();
        await dialog.getByRole('button', { name: 'Periodo', exact: true }).click();
        await dialog.getByRole('button', { name: 'Alert spese elevate' }).click();

        await expect(dialog.getByText('MAX([period_expenses], 0)')).toBeVisible();
    });

    test('tap su metrica pronta la seleziona subito nel widget', async ({ page }) => {
        await openCreateStep2(page);
        await page.getByRole('button', { name: 'Speso nel periodo' }).click();
        await expect(page.getByText(/Metrica «Speso nel periodo»/)).toBeVisible({ timeout: 10000 });
        await expect(page.getByRole('button', { name: 'Speso nel periodo' })).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByRole('group', { name: 'Periodo del widget' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Mese corrente', exact: true })).toBeVisible();
    });
});
