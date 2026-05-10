import { test, expect } from '@playwright/test';
import { e2eCredentials } from '../helpers';

/**
 * Test E2E — Accesso (Login)
 *
 * Copre: visualizzazione del form, validazione credenziali errate,
 * login con successo, link a recupero password e registrazione.
 */
test.describe('Autenticazione — Login', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accedi');
    });

    test('mostra il form di login con tutti i campi', async ({ page }) => {
        await expect(page).toHaveTitle(/Accedi/i);
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(page.getByLabel('Password')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Accedi' })).toBeVisible();
    });

    test('mostra errore per credenziali non valide', async ({ page }) => {
        await page.getByLabel('Email').fill('nonvalido@esempio.it');
        await page.getByLabel('Password').fill('passworderrata123');
        await page.getByRole('button', { name: 'Accedi' }).click();

        // L'errore Laravel su credenziali errate
        await expect(
            page.getByText(/credenziali.*non sono corrette/i)
        ).toBeVisible({ timeout: 8_000 });
    });

    test('effettua il login con credenziali corrette e va in dashboard', async ({ page }) => {
        const { email, password } = e2eCredentials;

        await page.getByLabel('Email').fill(email);
        await page.getByLabel('Password').fill(password);
        await page.getByRole('button', { name: 'Accedi' }).click();

        await expect(page).toHaveURL('/dashboard', { timeout: 15_000 });
    });

    test('mostra il link "Password dimenticata?"', async ({ page }) => {
        await expect(page.getByRole('link', { name: /password dimenticata/i })).toBeVisible();
    });

    test('il link "Password dimenticata?" porta a /forgot-password', async ({ page }) => {
        await page.getByRole('link', { name: /password dimenticata/i }).click();
        await expect(page).toHaveURL('/password-dimenticata');
    });

    test('mostra link per la registrazione', async ({ page }) => {
        await expect(page.getByRole('link', { name: /registrat/i })).toBeVisible();
    });

    test('il link di registrazione porta a /register', async ({ page }) => {
        await page.getByRole('link', { name: /registrat/i }).click();
        await expect(page).toHaveURL('/registrati');
    });
});
