import { test, expect } from '@playwright/test';
import { e2eCredentials } from '../helpers';

/**
 * Test E2E — Logout
 *
 * Copre il flusso completo di logout: login manuale → logout → redirect homepage.
 *
 * NOTA: esegue un login manuale per non invalidare la sessione condivisa dagli altri test.
 */
test('logout reindirizza alla homepage', async ({ page }) => {
    const { email, password } = e2eCredentials;

    await page.goto('/accedi');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.locator('[type="submit"]').click();
    await page.waitForURL('/dashboard', { timeout: 30_000 });

    // Forza reload per ottenere CSRF token fresco
    await page.goto('/dashboard');
    await page.waitForLoadState('networkidle');

    // Apri il menu utente e verifica che esista il form di logout
    await page.locator('[aria-label*="Menu utente"]').click();
    const logoutButton = page.locator('form[action*="disconnettiti"] button[type="submit"]');
    await logoutButton.waitFor({ state: 'visible', timeout: 5_000 });

    // Esegui il logout tramite form nativo con CSRF token
    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content') ?? '';
    await page.evaluate((token: string) => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/disconnettiti';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_token';
        input.value = token;
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }, csrfToken);

    await page.waitForURL('/', { timeout: 15_000 });
    await expect(page).toHaveURL('/');
});
