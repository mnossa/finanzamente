import { test, expect } from '@playwright/test';

/**
 * Test E2E — Smoke test autenticato
 *
 * Verifica rapida che l'utente autenticato riesca ad accedere alle sezioni principali.
 * Eseguito con la sessione salvata da auth.setup.ts.
 */
test.describe('Smoke test — navigazione autenticata', () => {
    test('la dashboard è raggiungibile dopo l\'autenticazione', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL('/dashboard');
        await expect(page).toHaveTitle(/Dashboard/i);
    });

    test('la pagina dei conti è raggiungibile', async ({ page }) => {
        await page.goto('/conti');
        await expect(page).toHaveURL('/conti');
    });

    test('la pagina delle transazioni è raggiungibile', async ({ page }) => {
        await page.goto('/transazioni');
        await expect(page).toHaveURL('/transazioni');
    });

    test('la pagina delle categorie è raggiungibile', async ({ page }) => {
        await page.goto('/categorie');
        await expect(page).toHaveURL('/categorie');
    });

    test('la pagina del profilo è raggiungibile', async ({ page }) => {
        await page.goto('/profilo');
        await expect(page).toHaveURL('/profilo');
    });
});

