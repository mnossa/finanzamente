import { test, expect, type Locator, type Page } from '@playwright/test';

/**
 * Test E2E — Profilo
 *
 * Copre: visualizzazione pagina profilo, form di aggiornamento dati,
 * form di cambio password, form di eliminazione account.
 */
test.describe('Profilo utente', () => {
    const savePrivacyPreferences = async (page: Page) => {
        await Promise.all([
            page.waitForResponse((response) =>
                response.url().includes('/profilo/consensi') &&
                response.request().method() === 'PATCH' &&
                response.status() >= 200 &&
                response.status() < 400
            ),
            page.getByRole('button', { name: /salva preferenze privacy/i }).click(),
        ]);
    };

    const setCheckboxState = async (checkbox: Locator, checked: boolean) => {
        const current = await checkbox.isChecked();
        if (current !== checked) {
            await checkbox.click();
        }
    };

    const getPrivacyCheckboxes = (page: Page) => ({
        marketing: page.getByLabel(/ricevi email marketing/i),
        analytics: page.getByLabel(/consenti analytics per miglioramento servizio/i),
    });

    test.beforeEach(async ({ page }) => {
        await page.goto('/profilo');
    });

    test('carica la pagina del profilo', async ({ page }) => {
        await expect(page).toHaveURL('/profilo');
        await expect(page).toHaveTitle(/Profilo/i);
    });

    test('mostra il form di aggiornamento informazioni profilo', async ({ page }) => {
        await expect(page.locator('#name')).toBeVisible();
        await expect(page.locator('#email')).toBeVisible();
    });

    test('il campo nome è precompilato con il nome utente corrente', async ({ page }) => {
        const nameField = page.locator('#name');
        const value = await nameField.inputValue();
        expect(value.length).toBeGreaterThan(0);
    });

    test('il campo email è precompilato con l\'email dell\'utente', async ({ page }) => {
        const emailField = page.locator('#email');
        const value = await emailField.inputValue();
        expect(value).toContain('@');
    });

    test('il pulsante "Salva" è visibile nel form profilo', async ({ page }) => {
        await expect(
            page.getByRole('button', { name: /^salva$/i }).first()
        ).toBeVisible();
    });

    test('mostra il selettore valuta predefinita e lo persiste dopo il salvataggio', async ({ page }) => {
        const currencySelect = page.locator('#default_currency_code');
        await expect(currencySelect).toBeVisible();

        // Seleziona GBP
        await currencySelect.selectOption('GBP');

        await Promise.all([
            page.waitForResponse((response) =>
                response.url().includes('/profilo') &&
                response.request().method() === 'PATCH' &&
                response.status() >= 200 &&
                response.status() < 400
            ),
            page.getByRole('button', { name: /^salva$/i }).first().click(),
        ]);

        await page.reload();
        await expect(currencySelect).toHaveValue('GBP');

        // Ripristina default (vuoto = EUR)
        await currencySelect.selectOption('');
        await Promise.all([
            page.waitForResponse((response) =>
                response.url().includes('/profilo') &&
                response.request().method() === 'PATCH' &&
                response.status() >= 200 &&
                response.status() < 400
            ),
            page.getByRole('button', { name: /^salva$/i }).first().click(),
        ]);
    });

    test('aggiorna il nome profilo con successo', async ({ page }) => {
        const nameField = page.locator('#name');
        await nameField.fill('Utente E2E Aggiornato');

        await Promise.all([
            page.waitForResponse((response) =>
                response.url().includes('/profilo') &&
                response.request().method() === 'PATCH' &&
                response.status() >= 200 &&
                response.status() < 400
            ),
            page.getByRole('button', { name: /^salva$/i }).first().click(),
        ]);

        await page.reload();
        await expect(nameField).toHaveValue('Utente E2E Aggiornato');

        // Ripristina il nome originale
        await nameField.fill('Utente E2E');
        await Promise.all([
            page.waitForResponse((response) =>
                response.url().includes('/profilo') &&
                response.request().method() === 'PATCH' &&
                response.status() >= 200 &&
                response.status() < 400
            ),
            page.getByRole('button', { name: /^salva$/i }).first().click(),
        ]);
    });

    test('mostra il form di cambio password', async ({ page }) => {
        const passwordSection = page.getByText(/aggiorna password|cambio password/i).first();
        await expect(passwordSection).toBeVisible();
    });

    test('sincronizza analytics da scelta anonima su pagine open dopo accesso autenticato', async ({ page }) => {
        const { analytics } = getPrivacyCheckboxes(page);
        const initialAnalyticsState = await analytics.isChecked();

        await page.evaluate(() => {
            window.localStorage.setItem('fm_analytics_consent', 'accepted');
        });

        await page.goto('/dashboard');
        await page.goto('/profilo');

        await expect.poll(async () => {
            await page.reload();
            return analytics.isChecked();
        }, { timeout: 10_000 }).toBe(true);

        await setCheckboxState(analytics, initialAnalyticsState);
        await savePrivacyPreferences(page);
    });

    test('marketing si salva, persiste e revoca totale resetta i flag opzionali', async ({ page }) => {
        const { marketing, analytics } = getPrivacyCheckboxes(page);

        const initialMarketing = await marketing.isChecked();
        const initialAnalytics = await analytics.isChecked();

        await setCheckboxState(marketing, !initialMarketing);
        await savePrivacyPreferences(page);

        await page.reload();
        await expect(marketing).toBeChecked({ checked: !initialMarketing });

        await Promise.all([
            page.waitForResponse((response) =>
                response.url().includes('/profilo/consensi/revoca-opzionali') &&
                response.request().method() === 'POST'
            ),
            page.getByRole('button', { name: /revoca.*consensi opzionali/i }).click(),
        ]);

        await expect.poll(async () => {
            await page.reload();
            return {
                marketing: await marketing.isChecked(),
                analytics: await analytics.isChecked(),
            };
        }, { timeout: 10_000 }).toEqual({
            marketing: false,
            analytics: false,
        });

        await setCheckboxState(marketing, initialMarketing);
        await setCheckboxState(analytics, initialAnalytics);
        await savePrivacyPreferences(page);
    });
});
