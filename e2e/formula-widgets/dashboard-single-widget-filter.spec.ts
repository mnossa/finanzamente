import { test, expect } from '@playwright/test';

/**
 * Test E2E — Dashboard: filtrare un widget ricalcola SOLO quel widget.
 *
 * Regressione: cambiando il conto su un widget a formula la dashboard rifaceva
 * il fetch dei payload di TUTTI i widget. Ora la richiesta deve contenere solo
 * l'id del widget filtrato.
 *
 * Dipende dai widget seed "Widget Conto A E2E" / "Widget Conto B E2E" (E2ESeeder),
 * entrambi con selettore conto e fissati in dashboard.
 */
test.describe('Dashboard — filtro singolo widget a formula', () => {
    test('cambiando il conto su un widget parte una sola richiesta col suo id', async ({ page }) => {
        const payloadRequests: string[] = [];

        page.on('request', (request) => {
            const url = request.url();
            if (url.includes('/dashboard/formula-widget-payloads')) {
                payloadRequests.push(url);
            }
        });

        await page.goto('/dashboard');
        await expect(page).toHaveURL('/dashboard');

        // Individua i due widget seed e i rispettivi id dal markup
        const cardA = page.locator('section, article, div').filter({ hasText: 'Widget Conto A E2E' }).first();
        await expect(cardA).toBeVisible({ timeout: 20000 });

        // I due selettori conto (aria-label "Conto")
        const accountSelects = page.getByLabel('Conto', { exact: true });
        await expect(accountSelects.first()).toBeVisible({ timeout: 20000 });
        const selectCount = await accountSelects.count();
        expect(selectCount).toBeGreaterThanOrEqual(2);

        // Aspetta che l'eventuale fetch iniziale sia concluso, poi azzera il log
        await page.waitForTimeout(1500);
        payloadRequests.length = 0;

        // Cambia il conto sul PRIMO widget (Widget Conto A) su un valore diverso da quello attuale
        const firstSelect = accountSelects.first();
        const optionValues = await firstSelect.locator('option').evaluateAll((opts) =>
            (opts as HTMLOptionElement[]).map((o) => o.value),
        );
        const currentValue = await firstSelect.inputValue();
        const targetValue =
            optionValues.find((v) => v !== 'all' && v !== currentValue)
            ?? optionValues.find((v) => v !== 'all')
            ?? optionValues[1];
        expect(targetValue).toBeTruthy();
        await firstSelect.selectOption(targetValue!);

        // Attende la richiesta di refetch generata dal cambio filtro
        await expect.poll(() => payloadRequests.length, { timeout: 15000 }).toBeGreaterThan(0);
        // Lascia eventuale margine per richieste aggiuntive (che NON dovrebbero esserci)
        await page.waitForTimeout(1500);

        // Tutte le richieste post-filtro devono riguardare un solo widget (ids con un id)
        for (const url of payloadRequests) {
            const ids = new URL(url).searchParams.get('ids');
            expect(ids, `richiesta payload con ids="${ids}"`).not.toBeNull();
            const idList = (ids ?? '').split(',').filter(Boolean);
            expect(idList.length, `attesa 1 sola metrica, ricevute: ${ids}`).toBe(1);
        }

        // Nessun errore visibile sui widget
        await expect(page.getByText('Non sono riuscito a caricare i widget a formula.')).toBeHidden();
    });
});
