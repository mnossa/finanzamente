import { expect, type Page } from '@playwright/test';

/**
 * Credenziali utente E2E (configurabili tramite variabili d'ambiente).
 * Corrispondono all'utente creato da E2ESeeder.
 */
export const e2eCredentials = {
    email: process.env.E2E_USER_EMAIL ?? 'e2e@finanzamente.test',
    password: process.env.E2E_USER_PASSWORD ?? 'password',
};

/**
 * Seleziona un'opzione da una <select> tramite il suo CSS selector (es. '#field_id'),
 * cercando un'opzione il cui testo corrisponde alla regex.
 * Se nessuna opzione corrisponde, seleziona la prima opzione valorizzata (fallback).
 * Attende che le opzioni siano caricate dinamicamente prima di procedere.
 */
export async function selectOptionByText(
    page: Page,
    selectCssSelector: string,
    textPattern: RegExp
): Promise<void> {
    const select = page.locator(selectCssSelector);
    const options = select.locator('option');

    await expect(select).toBeVisible();
    await expect.poll(
        async () => options.count(),
        { timeout: 10_000 }
    ).toBeGreaterThan(1);

    const count = await options.count();

    for (let i = 0; i < count; i++) {
        const text = (await options.nth(i).textContent()) ?? '';
        if (textPattern.test(text)) {
            const value = await options.nth(i).getAttribute('value');
            if (value) {
                await select.selectOption(value);
                return;
            }
        }
    }

    // Fallback robusto: prima opzione non vuota
    for (let i = 0; i < count; i++) {
        const value = await options.nth(i).getAttribute('value');
        if (value) {
            await select.selectOption(value);
            return;
        }
    }

    throw new Error(`Opzione selezionabile non trovata per "${selectCssSelector}"`);
}
