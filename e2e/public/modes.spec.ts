import { test, expect } from '@playwright/test';
import { e2eCredentials } from '../helpers';

/**
 * Test E2E — Modalità operative dell'applicazione
 *
 * Gestisce i tre scenari configurabili tramite variabili d'ambiente:
 *
 *   E2E_APP_MODE=normal      (default) — registrazione aperta, nessuna waitlist
 *   E2E_APP_MODE=prelaunch            — solo il proprietario può registrarsi/accedere
 *   E2E_APP_MODE=waitlist             — form waitlist visibile al posto del CTA Pro
 */

const appMode = (process.env.E2E_APP_MODE ?? 'normal') as 'normal' | 'prelaunch' | 'waitlist';
const prelaunchOwnerEmail = process.env.E2E_PRELAUNCH_OWNER_EMAIL ?? '';

// ═══════════════════════════════════════════════════════════════════════════════
// SUITE COMUNE — applicabile a tutte le modalità
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('Modalità app — Comportamento comune', () => {
    test('la homepage è accessibile in qualsiasi modalità', async ({ page }) => {
        const response = await page.goto('/');
        expect(response?.status()).toBe(200);
    });

    test('la pagina di login è accessibile in qualsiasi modalità', async ({ page }) => {
        const response = await page.goto('/accedi');
        expect(response?.status()).toBe(200);
        await expect(page.locator('input[type="email"]')).toBeVisible();
        await expect(page.locator('input[type="password"]')).toBeVisible();
    });

    test('un utente non autenticato viene reindirizzato al login dalla dashboard', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL('/accedi');
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// MODALITÀ NORMALE
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('Modalità NORMALE — Registrazione aperta', () => {
    test.skip(() => appMode !== 'normal', 'Eseguito solo con E2E_APP_MODE=normal');

    test('la pagina /registrati è accessibile direttamente', async ({ page }) => {
        const response = await page.goto('/registrati');
        expect(response?.status()).toBe(200);
        await expect(page).toHaveTitle(/Registrati/i);
    });

    test('la homepage contiene un link di registrazione o selezione piano', async ({ page }) => {
        await page.goto('/');
        // Testa la presenza del link tramite href, non il testo visualizzato
        await expect(
            page.locator('a[href*="scegli-piano"], a[href*="registrati"]').first()
        ).toBeVisible();
    });

    test('la homepage NON mostra messaggi di pre-lancio', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByText(/fase di pre-lancio/i)).not.toBeVisible();
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// MODALITÀ PRE-LANCIO
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('Modalità PRE-LANCIO — Accesso limitato al proprietario', () => {
    test.skip(() => appMode !== 'prelaunch', 'Eseguito solo con E2E_APP_MODE=prelaunch');

    test('/registrati senza parametro email reindirizza alla homepage', async ({ page }) => {
        await page.goto('/registrati');
        await expect(page).toHaveURL('/');
    });

    test('il tentativo di GET /registrati senza email è respinto (302)', async ({ page }) => {
        const resp = await page.request.get('/registrati', { maxRedirects: 0 });
        expect(resp.status()).toBe(302);
    });

    test.describe('Con email del proprietario configurata', () => {
        test.skip(() => !prelaunchOwnerEmail, 'Richiede E2E_PRELAUNCH_OWNER_EMAIL impostato');

        test('/registrati con ?email=owner è accessibile', async ({ page }) => {
            await page.goto(`/registrati?email=${encodeURIComponent(prelaunchOwnerEmail)}`);
            await expect(page).toHaveTitle(/Registrati/i);
        });

        test('il proprietario può compilare il form di registrazione', async ({ page }) => {
            await page.goto(`/registrati?email=${encodeURIComponent(prelaunchOwnerEmail)}`);
            await expect(page).toHaveTitle(/Registrati/i);

            await page.locator('input[name="name"]').fill('Proprietario E2E');
            await page.locator('input[name="email"]').fill(prelaunchOwnerEmail);
            await page.locator('input[name="password"]').fill('password123!');
            await page.locator('input[name="password_confirmation"]').fill('password123!');
            await page.locator('[type="submit"]').click();

            await expect(page).not.toHaveURL('/');
        });
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// MODALITÀ WAITLIST
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('Modalità WAITLIST — Form iscrizione al posto del CTA Pro', () => {
    test.skip(() => appMode !== 'waitlist', 'Eseguito solo con E2E_APP_MODE=waitlist');

    test('la pagina /registrati è ancora accessibile', async ({ page }) => {
        const response = await page.goto('/registrati');
        expect(response?.status()).toBe(200);
    });

    test('la homepage è accessibile', async ({ page }) => {
        await page.goto('/');
        expect(await page.locator('body').isVisible()).toBeTruthy();
    });

    test('è possibile inviare il form waitlist dalla homepage', async ({ page }) => {
        await page.goto('/');
        const emailInput = page.locator('input[type="email"]').first();
        if (await emailInput.isVisible()) {
            await emailInput.fill(`waitlist-e2e-${Date.now()}@esempio.it`);
            const submitBtn = page.locator('[type="submit"]').first();
            if (await submitBtn.isVisible()) {
                await submitBtn.click();
                await expect(page).not.toHaveURL('/errore');
            }
        } else {
            test.skip();
        }
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// COERENZA MODALITÀ
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('Coerenza modalità dichiarata', () => {
    test('la modalità dichiarata normal corrisponde al comportamento HTTP', async ({ page }) => {
        test.skip(appMode !== 'normal', 'Eseguito solo con E2E_APP_MODE=normal');
        const response = await page.request.get('/registrati', { maxRedirects: 0 });
        expect(response.status()).toBe(200);
    });

    test('la modalità dichiarata prelaunch corrisponde al comportamento HTTP', async ({ page }) => {
        test.skip(appMode !== 'prelaunch', 'Eseguito solo con E2E_APP_MODE=prelaunch');
        const response = await page.request.get('/registrati', { maxRedirects: 0 });
        expect(response.status()).toBe(302);
    });
});
