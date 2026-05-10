import { test, expect } from '@playwright/test';
import { e2eCredentials } from '../helpers';

/**
 * Test E2E — Logout
 *
 * Copre il flusso completo di logout: login manuale → verifica presenza
 * bottone "Esci" nel dropdown → logout via form nativo → redirect homepage.
 *
 * NOTA: questo test esegue un login manuale anziché usare lo storageState
 * condiviso, in modo da non invalidare la sessione usata dagli altri test
 * autenticati.
 */
test('logout esegue il logout e reindirizza alla homepage', async ({ page }) => {
    const { email, password } = e2eCredentials;

    // Login manuale
    await page.goto('/accedi');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Accedi' }).click();

    // Attendi il completamento del login e della navigazione al dashboard
    await page.waitForURL('/dashboard', { timeout: 30_000 });
    // Forza un reload completo per ottenere un CSRF token fresco (Inertia usa
    // navigazioni client-side che non aggiornano il meta tag csrf-token)
    await page.goto('/dashboard');
    await page.waitForLoadState('networkidle');

    // Apri il menu utente e verifica che il bottone Esci sia presente (test UI)
    await page.locator('[aria-label*="Menu utente"]').click();
    const logoutButton = page.locator('form[action*="disconnettiti"] button[type="submit"]');
    await logoutButton.waitFor({ state: 'visible', timeout: 5_000 });
    await expect(logoutButton).toBeVisible();

    // Esegui il logout tramite form nativo (aggiungendo il CSRF token al body),
    // più affidabile del click React in contesto E2E headless.
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

    // Il controller reindirizza alla homepage dopo il logout
    await page.waitForURL('/', { timeout: 15_000 });
    await expect(page).toHaveURL('/');
});
