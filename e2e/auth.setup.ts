import { test as setup, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

/**
 * File dove viene salvato lo stato di autenticazione (cookie + localStorage).
 * Viene letto dai progetti "autenticato-desktop" e "autenticato-mobile".
 */
const __filename = fileURLToPath(import.meta.url);
const __dirname  = path.dirname(__filename);
const authFile   = path.join(__dirname, '.auth/user.json');

setup('autenticazione e salvataggio stato sessione', async ({ page }) => {
    const email    = process.env.E2E_USER_EMAIL    ?? 'e2e@finanzamente.test';
    const password = process.env.E2E_USER_PASSWORD ?? 'password';

    // Assicura che la directory esista
    fs.mkdirSync(path.dirname(authFile), { recursive: true });

    await page.goto('/login');
    await expect(page).toHaveTitle(/Accedi/i);

    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: 'Accedi' }).click();

    // Dopo il login può esserci un redirect a /households o direttamente /dashboard
    await page.waitForURL(/\/(dashboard|households)/, { timeout: 15_000 });

    // Se l'utente non ha una household attiva, viene portato su /households/select
    if (page.url().includes('/households')) {
        // Attende la risoluzione automatica (E2ESeeder imposta l'household attiva)
        await page.waitForURL('/dashboard', { timeout: 10_000 });
    }

    await expect(page).toHaveURL('/dashboard');

    // Salva lo stato (cookie di sessione) per i test successivi
    await page.context().storageState({ path: authFile });
});
