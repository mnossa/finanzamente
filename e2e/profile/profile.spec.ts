import { test, expect, type Locator, type Page } from '@playwright/test';

/**
 * Test E2E — Profilo
 *
 * Copre: aggiornamento dati, cambio password, preferenze privacy.
 * Selettori strutturali (name) indipendenti da label/testo UI.
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
        // Le checkbox privacy non hanno name/id: uso regex minimale sulla keyword chiave
        // (resiliente al rewording della label, non al cambio della parola chiave del consenso)
        marketing: page.getByLabel(/marketing/i),
        analytics: page.getByLabel(/analytics/i),
    });

    test.beforeEach(async ({ page }) => {
        await page.goto('/profilo');
    });

    test('la pagina profilo si carica', async ({ page }) => {
        await expect(page).toHaveURL('/profilo');
        await expect(page).toHaveTitle(/Profilo/i);
    });

    test('il form profilo ha i campi nome ed email', async ({ page }) => {
        await expect(page.locator('input[name="name"]')).toBeVisible();
        await expect(page.locator('input[name="email"]')).toBeVisible();
    });

    test('il campo nome è precompilato', async ({ page }) => {
        const value = await page.locator('input[name="name"]').inputValue();
        expect(value.length).toBeGreaterThan(0);
    });

    test('il campo email è precompilato con un indirizzo valido', async ({ page }) => {
        const value = await page.locator('input[name="email"]').inputValue();
        expect(value).toContain('@');
    });

    test('aggiorna il nome profilo con successo', async ({ page }) => {
        await page.locator('input[name="name"]').fill('Utente E2E Aggiornato');

        await Promise.all([
            page.waitForResponse((response) =>
                response.url().includes('/profilo') &&
                response.request().method() === 'PATCH' &&
                response.status() >= 200 &&
                response.status() < 400
            ),
            page.locator('form').filter({ has: page.locator('input[name="name"]') })
                .locator('[type="submit"]').first().click(),
        ]);

        await page.reload();
        await expect(page.locator('input[name="name"]')).toHaveValue('Utente E2E Aggiornato');

        // Ripristina
        await page.locator('input[name="name"]').fill('Utente E2E');
        await Promise.all([
            page.waitForResponse((response) =>
                response.url().includes('/profilo') &&
                response.request().method() === 'PATCH' &&
                response.status() >= 200 &&
                response.status() < 400
            ),
            page.locator('form').filter({ has: page.locator('input[name="name"]') })
                .locator('[type="submit"]').first().click(),
        ]);
    });

    test('la pagina profilo contiene il form per cambiare password', async ({ page }) => {
        // Verifica strutturalmente: esiste un form con campo current_password
        await expect(page.locator('input[name="current_password"]')).toBeVisible();
    });

    test('sincronizza analytics da scelta anonima dopo accesso autenticato', async ({ page }) => {
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
        }, { timeout: 10_000 }).toEqual({ marketing: false, analytics: false });

        await setCheckboxState(marketing, initialMarketing);
        await setCheckboxState(analytics, initialAnalytics);
        await savePrivacyPreferences(page);
    });
});
