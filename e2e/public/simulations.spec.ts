import { test, expect } from '@playwright/test';

test.describe('Simulazioni pubbliche', () => {
    test('carica la pagina e mostra i tab principali', async ({ page }) => {
        await page.goto('/simulazioni');

        await expect(page).toHaveTitle(/Simulazioni Finanziarie/i);
        await expect(page.getByRole('heading', { name: /Simulazioni finanziarie/i })).toBeVisible();
        await expect(page.getByRole('tab', { name: /Interesse Composto/i })).toBeVisible();
        await expect(page.getByRole('tab', { name: /Fondo di Emergenza/i })).toBeVisible();
    });

    test('il link Home apre la homepage con navigazione completa', async ({ page }) => {
        await page.goto('/simulazioni');
        await page.getByRole('navigation').getByRole('link', { name: 'Home' }).click();
        await expect(page).toHaveURL(/\/$/);
        await expect(page).toHaveTitle(/Finanzamente/i);
        await expect(page.getByRole('heading', { level: 1 })).toContainText(/quadro finanziario sotto controllo/i);
        await expect(page.getByRole('navigation').getByRole('link', { name: /^accedi$/i })).toBeVisible();
    });

    test('footer homepage contiene link alle simulazioni', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('contentinfo').getByRole('link', { name: /Simulazioni finanziarie/i })).toBeVisible();
    });
});
