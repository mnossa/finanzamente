import { test, expect } from '@playwright/test';

/**
 * Test E2E — Pagine pubbliche
 *
 * Verifica che la homepage e le risorse pubbliche base
 * siano raggiungibili e contengano i contenuti attesi.
 */
test.describe('Homepage pubblica', () => {
    test('carica la homepage con titolo corretto', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveTitle(/FinanzaMente/i);
    });

    test('mostra il titolo principale (H1)', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('mostra il link "Inizia gratis ora" che punta a /select-plan', async ({ page }) => {
        await page.goto('/');
        // Prende il primo link corrispondente (possono esisterne più di uno nella pagina)
        const cta = page.getByRole('link', { name: /inizia gratis/i }).first();
        await expect(cta).toBeVisible();
        await expect(cta).toHaveAttribute('href', /select-plan|register/);
    });

    test('il link "Accedi" nella nav porta a /login', async ({ page }) => {
        await page.goto('/');
        // Cerca il link Accedi nella navigazione pubblica
        const loginLink = page.getByRole('link', { name: /^accedi$/i }).first();
        await expect(loginLink).toBeVisible();
        await loginLink.click();
        await expect(page).toHaveURL('/login');
    });

    test('robots.txt è accessibile e restituisce 200', async ({ page }) => {
        const response = await page.goto('/robots.txt');
        expect(response?.status()).toBe(200);
    });

    test('un utente non autenticato viene reindirizzato a /login se prova ad accedere alla dashboard', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL('/login');
    });
});
