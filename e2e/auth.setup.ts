import { test as setup, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { e2eCredentials } from './helpers';

/**
 * File dove viene salvato lo stato di autenticazione (cookie + localStorage).
 * Viene letto dai progetti "autenticato-desktop" e "autenticato-mobile".
 */
const __filename = fileURLToPath(import.meta.url);
const __dirname  = path.dirname(__filename);
const authFile   = path.join(__dirname, '.auth/user.json');

setup('autenticazione e salvataggio stato sessione', async ({ page }) => {
    const { email, password } = e2eCredentials;

    fs.mkdirSync(path.dirname(authFile), { recursive: true });

    await page.goto('/accedi');
    await expect(page).toHaveTitle(/Accedi/i);

    // Selettori strutturali: name/type legati al form HTTP, non al testo UI
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.locator('[type="submit"]').click();

    await page.waitForURL(/\/(dashboard|households)/, { timeout: 15_000 });

    if (page.url().includes('/households')) {
        await page.waitForURL('/dashboard', { timeout: 10_000 });
    }

    await expect(page).toHaveURL('/dashboard');

    await page.context().storageState({ path: authFile });
});
