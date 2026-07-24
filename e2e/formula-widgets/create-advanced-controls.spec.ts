import { test, expect, type Page } from '@playwright/test';

async function goToCreateStep(page: Page, step: 2 | 3) {
    await page.goto('/widget-formule/crea');
    await expect(page).toHaveURL(/\/widget-formule\/crea/);
    await page.getByRole('button', { name: 'Continua' }).click();
    await expect(page.getByRole('heading', { name: 'Scegli metrica e periodo' })).toBeVisible();

    // Metrica di periodo + «Mese corrente» → abilita «Mese scorrevole» in avanzate.
    await page.getByRole('button', { name: 'Speso nel periodo' }).click();
    await expect(page.getByText(/Metrica «Speso nel periodo»/)).toBeVisible({ timeout: 10000 });
    await page.getByRole('button', { name: 'Mese corrente', exact: true }).click();

    if (step === 3) {
        await page.getByRole('button', { name: 'Continua' }).click();
        await expect(page.getByRole('heading', { name: 'Nome e visualizzazione' })).toBeVisible();
    }
}

/**
 * Test E2E — Creazione widget a formula: controlli avanzati in anteprima.
 *
 * Verifica che, abilitando "Conto selezionabile" e "Mese scorrevole" nelle
 * Opzioni avanzate, l'anteprima mostri i controlli runtime (dropdown conto +
 * navigazione mesi) e che interagendoci l'anteprima resti coerente.
 *
 * Dipende dalla metrica seed "Bilancio Periodo E2E" (E2ESeeder).
 */
test.describe('Widget a formula — controlli avanzati in anteprima', () => {
    test('abilitando i controlli avanzati l’anteprima li mostra ed è interattiva', async ({ page }) => {
        await goToCreateStep(page, 3);

        // Apri le opzioni avanzate (step 3)
        await page.getByRole('button', { name: /Opzioni avanzate/i }).click();

        await page.getByRole('checkbox', { name: /Conto selezionabile/i }).check();
        await page.getByRole('checkbox', { name: /Mese scorrevole/i }).check();

        const preview = page.locator('div').filter({
            has: page.getByRole('heading', { name: 'Anteprima', exact: true }),
        }).last();

        const accountSelect = preview.getByLabel('Conto selezionabile', { exact: true });
        await expect(accountSelect).toBeVisible({ timeout: 15000 });

        const prevMonth = preview.getByRole('button', { name: 'Mese precedente' });
        const nextMonth = preview.getByRole('button', { name: 'Mese successivo' });
        await expect(prevMonth).toBeVisible();
        await expect(nextMonth).toBeVisible();

        const optionCount = await accountSelect.locator('option').count();
        expect(optionCount).toBeGreaterThan(1);
        await accountSelect.selectOption({ index: 1 });

        await expect(page.getByText('Anteprima non disponibile')).toBeHidden();
        await expect(accountSelect).toBeVisible();

        await prevMonth.click();
        await expect(page.getByText('Anteprima non disponibile')).toBeHidden();
        await expect(page.getByRole('button', { name: 'Mese successivo' })).toBeEnabled();
    });
});
