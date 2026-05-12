import { expect, type Locator, type Page } from '@playwright/test';

/**
 * Link con stesso href possono esistere in header desktop e drawer mobile:
 * solo l’istanza visibile è cliccabile nei test.
 */
export function visibleHrefLocator(page: Page, hrefSubstring: string): Locator {
    return page.locator(`a[href*="${hrefSubstring}"]`).filter({ visible: true }).first();
}

/**
 * Credenziali utente E2E (configurabili tramite variabili d'ambiente).
 * Corrispondono all'utente creato da E2ESeeder.
 */
export const e2eCredentials = {
    email: process.env.E2E_USER_EMAIL ?? 'e2e@finanzamente.test',
    password: process.env.E2E_USER_PASSWORD ?? 'password',
};

/**
 * Esegue il login programmatico compilando il form di accesso.
 * Usa selettori strutturali (type/name) che non dipendono da testo UI.
 */
export async function loginAs(
    page: Page,
    email = e2eCredentials.email,
    password = e2eCredentials.password
): Promise<void> {
    await page.goto('/accedi');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.locator('[type="submit"]').click();
    await page.waitForURL(/\/(dashboard|households)/, { timeout: 15_000 });
    if (page.url().includes('/households')) {
        await page.waitForURL('/dashboard', { timeout: 10_000 });
    }
}

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
