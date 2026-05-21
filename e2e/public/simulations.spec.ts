import { test, expect } from '@playwright/test';

test.describe('Simulazioni pubbliche', () => {
    test('carica la pagina e mostra i tab principali', async ({ page }) => {
        await page.goto('/simulazioni');

        await expect(page).toHaveTitle(/Simulazioni Finanziarie/i);
        await expect(page.getByRole('heading', { name: /Simulazioni finanziarie/i })).toBeVisible();
        await expect(page.getByRole('tab', { name: /Interesse Composto/i })).toBeVisible();
        await expect(page.getByRole('tab', { name: /Fondo di Emergenza/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /Registrati gratis/i })).toBeVisible();
    });
});
