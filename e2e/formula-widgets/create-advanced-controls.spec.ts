import { test, expect } from '@playwright/test';

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
    test.beforeEach(async ({ page }) => {
        await page.goto('/widget-formule/crea');
        await expect(page).toHaveURL(/\/widget-formule\/crea/);
    });

    test('abilitando i controlli avanzati l’anteprima li mostra ed è interattiva', async ({ page }) => {
        // La metrica seed deve essere selezionabile
        await expect(page.locator('#financial_variable_id')).toBeVisible();

        // Apri le opzioni avanzate
        await page.getByRole('button', { name: /Opzioni avanzate/i }).click();

        // Abilita conto selezionabile e mese scorrevole
        await page.getByRole('checkbox', { name: /Conto selezionabile/i }).check();
        await page.getByRole('checkbox', { name: /Mese scorrevole/i }).check();

        // Pannello anteprima
        const preview = page.getByRole('region', { name: /Anteprima/i }).or(
            page.locator('text=Anteprima').first(),
        );

        // Il dropdown conto compare nell'anteprima (aria-label "Conto")
        const accountSelect = page.getByLabel('Conto', { exact: true });
        await expect(accountSelect).toBeVisible({ timeout: 15000 });

        // I controlli di navigazione mese compaiono
        const prevMonth = page.getByRole('button', { name: 'Mese precedente' });
        const nextMonth = page.getByRole('button', { name: 'Mese successivo' });
        await expect(prevMonth).toBeVisible();
        await expect(nextMonth).toBeVisible();

        // Interazione: seleziona un conto specifico → anteprima si aggiorna senza errori
        const optionCount = await accountSelect.locator('option').count();
        expect(optionCount).toBeGreaterThan(1);
        await accountSelect.selectOption({ index: 1 });

        // Nessun errore di anteprima dopo il cambio conto
        await expect(page.getByText('Anteprima non disponibile')).toBeHidden();
        // Il controllo conto resta presente e riflette la scelta
        await expect(accountSelect).toBeVisible();

        // Interazione: vai al mese precedente → anteprima resta valida
        await prevMonth.click();
        await expect(page.getByText('Anteprima non disponibile')).toBeHidden();
        await expect(page.getByRole('button', { name: 'Mese successivo' })).toBeEnabled();
    });
});
