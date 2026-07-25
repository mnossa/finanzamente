import { test, expect } from '@playwright/test';

test.describe('Widget a formula — ricette e metriche', () => {
    test('Confronto metriche offre solo viste a barre e metriche di periodo', async ({ page }) => {
        await page.goto('/widget-formule/crea');
        await page.getByRole('button', { name: 'Confronto metriche' }).click();
        await page.getByRole('button', { name: 'Continua' }).click();

        await expect(page.getByRole('heading', { name: 'Scegli metrica e periodo' })).toBeVisible();
        await expect(page.getByText(/Solo metriche compatibili/i)).toBeVisible();
        await expect(page.getByRole('button', { name: 'Speso nel periodo' })).toBeVisible();
        // Metriche patrimonio non compatibili con confronto
        await expect(page.getByRole('button', { name: 'Liquidità attuale' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Mostra tutte le metriche' })).toHaveCount(0);

        await page.getByRole('button', { name: 'Speso nel periodo' }).click();
        await page.getByRole('button', { name: 'Mese corrente', exact: true }).click();
        await page.getByRole('button', { name: 'Continua' }).click();

        const vistaGroup = page.getByRole('group', { name: 'Tipo di visualizzazione' });
        await expect(vistaGroup.getByRole('button', { name: /Barre/i })).toBeVisible();
        await expect(vistaGroup.getByRole('button', { name: /Indicatore|Linea|Tabella|Avanzamento/i })).toHaveCount(0);
    });
});
