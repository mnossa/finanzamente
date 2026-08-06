import { test, expect } from '@playwright/test';

/**
 * Test E2E — Homepage pubblica (build OSS)
 */
test.describe('Homepage pubblica', () => {
    test('carica la homepage con titolo corretto', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveTitle(/Finanzamente/i);
    });

    test('mostra il titolo principale (H1)', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('mostra il link "Inizia gratis" verso la registrazione', async ({ page }) => {
        await page.goto('/');
        const cta = page.getByRole('link', { name: /inizia gratis/i }).first();
        await expect(cta).toBeVisible();
        await expect(cta).toHaveAttribute('href', /registrati/);
    });

    test('il link "Accedi" nella nav porta a /login', async ({ page }) => {
        await page.goto('/');
        const loginLink = page.getByRole('link', { name: /^accedi$/i }).first();
        await expect(loginLink).toBeVisible();
        await loginLink.click();
        await expect(page).toHaveURL('/accedi');
    });

    test('mostra i quattro pilastri funzionali', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('heading', { name: /Quattro aree, un unico quadro/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /Registra senza perderci tempo/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /Conti chiari anche in due/i })).toBeVisible();
    });

    test('la FAQ si apre al click e mostra la risposta', async ({ page }) => {
        await page.goto('/');
        const question = page.getByRole('group').filter({ hasText: /Cosa posso fare con Finanzamente/i }).first();
        await expect(question).toBeVisible();
        await question.getByText(/Cosa posso fare con Finanzamente/i).click();
        await expect(question.getByText(/open source/i)).toBeVisible();
    });

    test('la homepage non nomina le operazioni bancarie', async ({ page }) => {
        await page.goto('/');
        const main = await page.locator('main').innerText();
        expect(main).not.toMatch(/banc(a|he|ari|aria|arie)/i);
        expect(main).not.toMatch(/sincronizzazione/i);
    });

    test('robots.txt è accessibile e restituisce 200', async ({ page }) => {
        const response = await page.goto('/robots.txt');
        expect(response?.status()).toBe(200);
    });

    test('un utente non autenticato viene reindirizzato a /login se prova ad accedere alla dashboard', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL('/accedi');
    });
});
