import { test, expect } from '@playwright/test';

/**
 * Test E2E — Conti
 *
 * Copre: lista conti, navigazione al form di creazione,
 * creazione di un nuovo conto, verifica nella lista.
 */
test.describe('Conti', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/conti');
    });

    test('carica la pagina dei conti', async ({ page }) => {
        await expect(page).toHaveURL('/accounts');
        await expect(page).toHaveTitle(/conti/i);
    });

    test('mostra il pulsante "Nuovo Conto"', async ({ page }) => {
        await expect(
            page.getByRole('link', { name: /nuovo conto/i })
        ).toBeVisible();
    });

    test('il pulsante "Nuovo Conto" porta al form di creazione', async ({ page }) => {
        await page.getByRole('link', { name: /nuovo conto/i }).click();
        await expect(page).toHaveURL('/accounts/create');
        await expect(page).toHaveTitle(/nuovo conto/i);
    });

    test('il form di creazione conto ha i campi necessari', async ({ page }) => {
        await page.goto('/accounts/create');
        await expect(page.locator('#name')).toBeVisible();
        await expect(page.getByRole('button', { name: /crea conto/i })).toBeVisible();
    });

    test('crea un nuovo conto e lo mostra nella lista', async ({ page }) => {
        const nomeConto = `Conto E2E ${Date.now()}`;

        await page.goto('/accounts/create');
        await page.locator('#name').fill(nomeConto);

        // Saldo iniziale: campo opzionale, imposta 0 se non valorizzato
        const saldoInput = page.locator('#initial_balance');
        if (await saldoInput.isVisible()) {
            await saldoInput.fill('100');
        }

        await page.getByRole('button', { name: /crea conto/i }).click();

        // Dopo la creazione dovrebbe reindirizzare ai conti o al dettaglio
        await expect(page).toHaveURL(/\/accounts/, { timeout: 10_000 });

        // Verifica che il nome del conto appaia da qualche parte nella pagina
        await expect(page.getByText(nomeConto)).toBeVisible({ timeout: 8_000 });
    });

    test('validazione: errore se il nome conto è vuoto', async ({ page }) => {
        await page.goto('/accounts/create');
        await page.getByRole('button', { name: /crea conto/i }).click();

        // Errore HTML5 o messaggio di validazione
        const nameInput = page.locator('#name');
        await expect(nameInput).toBeVisible();
        // Il browser non permette l'invio senza nome se il campo è required
        const url = page.url();
        expect(url).toContain('/create');
    });
});
