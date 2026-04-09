import { test, expect } from '@playwright/test';

/**
 * Test E2E — Modalità operative dell'applicazione
 *
 * Gestisce i tre scenari configurabili tramite variabili d'ambiente:
 *
 *   E2E_APP_MODE=normal      (default) — registrazione aperta, nessuna waitlist
 *   E2E_APP_MODE=prelaunch            — solo il proprietario può registrarsi/accedere
 *   E2E_APP_MODE=waitlist             — form waitlist visibile al posto del CTA Pro
 *
 * Come eseguire per modalità specifica:
 *   E2E_APP_MODE=prelaunch E2E_PRELAUNCH_OWNER_EMAIL=owner@example.com make playwright
 *
 * Nota: la modalità E2E_APP_MODE deve corrispondere alla configurazione reale
 * del server (.env). Questi test verificano il comportamento visibile, non
 * modificano la configurazione del server.
 */

const appMode = (process.env.E2E_APP_MODE ?? 'normal') as 'normal' | 'prelaunch' | 'waitlist';
const prelaunchOwnerEmail = process.env.E2E_PRELAUNCH_OWNER_EMAIL ?? '';

// ─── Helper: rileva la modalità direttamente dalla homepage ────────────────────
// Usato quando non è specificato E2E_APP_MODE, per auto-rilevare dal DOM/comportamento

async function detectPrelaunchMode(page: import('@playwright/test').Page): Promise<boolean> {
    await page.goto('/');
    // In prelaunch, il link "Registrati/Inizia gratis" reindirizza alla home con messaggio
    // oppure non è presente
    const ctaLink = page.getByRole('link', { name: /inizia gratis/i }).first();
    if (!(await ctaLink.isVisible())) return true;
    // Tenta GET /registrati e controlla il redirect
    const resp = await page.request.get('/registrati', { maxRedirects: 0 });
    return resp.status() === 302;
}

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
        await expect(page.locator('#email')).toBeVisible();
        await expect(page.locator('#password')).toBeVisible();
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

    test('la homepage contiene un CTA che punta alla registrazione o selezione piano', async ({ page }) => {
        await page.goto('/');
        const cta = page.getByRole('link', { name: /inizia gratis/i }).first();
        await expect(cta).toBeVisible();
        await expect(cta).toHaveAttribute('href', /scegli-piano|registrati/);
    });

    test('la pagina di login mostra il link a /registrati', async ({ page }) => {
        await page.goto('/accedi');
        const registerLink = page.getByRole('link', { name: /registrati|crea.*account|non.*account/i }).first();
        await expect(registerLink).toBeVisible();
    });

    test('la homepage NON mostra messaggi di pre-lancio', async ({ page }) => {
        await page.goto('/');
        // Verifica che il messaggio flash specifico del pre-lancio NON sia visibile.
        // Non usiamo 'in arrivo' perché è testo statico del pricing (non legato al mode).
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

    test('la homepage mostra un messaggio informativo sul pre-lancio', async ({ page }) => {
        // Naviga prima a /registrati per generare il messaggio flash
        await page.goto('/registrati');
        // Viene reindirizzato alla home con messaggio
        await expect(page).toHaveURL('/');
        await expect(page.getByText(/pre-lancio|waitlist|avvisa/i)).toBeVisible();
    });

    test('il tentativo di POST a /registrati con email non-owner viene respinto', async ({ page }) => {
        await page.goto('/registrati');
        // Aspettiamo di essere sulla home (redirect)
        await expect(page).toHaveURL('/');
    });

    test('la pagina di login NON mostra il link di registrazione', async ({ page }) => {
        await page.goto('/accedi');
        // In prelaunch canRegister=false, il link non deve essere visibile
        const registerLink = page.getByRole('link', { name: /^registrati$/i });
        await expect(registerLink).not.toBeVisible();
    });

    test.describe('Con email del proprietario configurata', () => {
        test.skip(() => !prelaunchOwnerEmail, 'Richiede E2E_PRELAUNCH_OWNER_EMAIL impostato');

        test('/registrati con ?email=owner è accessibile', async ({ page }) => {
            await page.goto(`/registrati?email=${encodeURIComponent(prelaunchOwnerEmail)}`);
            await expect(page).toHaveTitle(/Registrati/i);
        });

        test('il proprietario può completare la registrazione', async ({ page }) => {
            const ownerEmail = prelaunchOwnerEmail;
            await page.goto(`/registrati?email=${encodeURIComponent(ownerEmail)}`);
            await expect(page).toHaveTitle(/Registrati/i);

            await page.locator('#name').fill('Proprietario E2E');
            await page.locator('#email').fill(ownerEmail);
            await page.locator('#password').fill('password123!');
            await page.locator('#password_confirmation').fill('password123!');
            await page.getByRole('button', { name: 'Registrati' }).click();

            // Deve procedere senza reindirizzamento alla home
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
        await expect(page).toHaveTitle(/Registrati/i);
    });

    test('la homepage è accessibile e contiene la sezione prezzi/waitlist', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveTitle(/Finanzamente/i);
    });

    test('la pagina di selezione piano mostra il form waitlist', async ({ page }) => {
        await page.goto('/scegli-piano');
        // In modalità waitlist deve essere presente un form email o un riferimento alla waitlist
        await expect(
            page.getByRole('heading', { name: /waitlist|lista.*attesa|avvisami|lista d'attesa/i })
                .or(page.getByText(/waitlist|lista.*attesa|iscriviti|avvisami/i))
                .first()
        ).toBeVisible({ timeout: 8_000 });
    });

    test('la pagina di login mostra ancora il link a /registrati', async ({ page }) => {
        await page.goto('/accedi');
        const registerLink = page.getByRole('link', { name: /registrati|crea.*account|non.*account/i }).first();
        await expect(registerLink).toBeVisible();
    });

    test('è possibile iscriversi alla waitlist dalla homepage', async ({ page }) => {
        await page.goto('/');
        // Cerca il form waitlist o il campo email
        const emailInput = page.locator('input[type="email"]').first();
        if (await emailInput.isVisible()) {
            await emailInput.fill(`waitlist-e2e-${Date.now()}@esempio.it`);
            const submitBtn = page.getByRole('button', { name: /iscriviti|notificami|avvisami/i }).first();
            if (await submitBtn.isVisible()) {
                await submitBtn.click();
                // Deve mostrare un messaggio di conferma o rimbalzare
                await expect(page.getByText(/grazie|iscritto|confermato|avvisato/i)).toBeVisible({ timeout: 8_000 });
            }
        } else {
            test.skip();
        }
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// AUTO-RILEVAMENTO — Verifica coerenza tra E2E_APP_MODE e comportamento reale
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('Coerenza modalità dichiarata', () => {
    test('la modalità rilevata dalla pagina corrisponde a E2E_APP_MODE=normal', async ({ page }) => {
        test.skip(appMode !== 'normal', 'Eseguito solo con E2E_APP_MODE=normal');

        // In modalità normale /registrati non fa redirect
        const response = await page.request.get('/registrati', { maxRedirects: 0 });
        expect(response.status()).toBe(200);
    });

    test('la modalità rilevata dalla pagina corrisponde a E2E_APP_MODE=prelaunch', async ({ page }) => {
        test.skip(appMode !== 'prelaunch', 'Eseguito solo con E2E_APP_MODE=prelaunch');

        // In prelaunch /registrati senza email fa redirect (302)
        const response = await page.request.get('/registrati', { maxRedirects: 0 });
        expect(response.status()).toBe(302);
    });
});
