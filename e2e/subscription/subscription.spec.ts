import { test, expect } from '@playwright/test';

test.describe('Abbonamento', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/profilo/abbonamento');
    });

    test('carica pagina abbonamento', async ({ page }) => {
        await expect(page).toHaveURL('/profilo/abbonamento');
        await expect(page).toHaveTitle(/abbonamento/i);
        await expect(page.getByRole('heading', { name: /piano attuale/i })).toBeVisible();
    });

    test('mostra stato Pro attivo e sezione gestione', async ({ page }) => {
        await expect(page.getByText(/pro/i).first()).toBeVisible();
        await expect(page.getByText(/attivo|in attesa/i).first()).toBeVisible();
        await expect(page.getByRole('heading', { name: /gestione abbonamento/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /^aggiorna$/i })).toBeVisible();
    });

    test('apre e salva form dati fatturazione', async ({ page }) => {
        await page.getByRole('button', { name: /modifica/i }).click();

        await expect(page.locator('#billing_name')).toBeVisible();
        await expect(page.locator('#billing_email')).toBeVisible();

        await page.locator('#billing_name').fill('Utente E2E Billing');
        await page.locator('#billing_email').fill('e2e@finanzamente.test');

        await page.getByRole('button', { name: /salva dati fatturazione/i }).click();
        await expect(page.getByText(/salvato/i)).toBeVisible({ timeout: 10_000 });
    });

    test('checkout mock + webhook mock aggiorna stato abbonamento', async ({ page }) => {
        const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        expect(csrfToken).toBeTruthy();

        const checkoutResponse = await page.request.post('/abbonamento/checkout', {
            form: { billing_cycle: 'monthly' },
            headers: {
                'X-CSRF-TOKEN': csrfToken ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            maxRedirects: 0,
            failOnStatusCode: false,
        });
        expect([302, 303]).toContain(checkoutResponse.status());

        const location = checkoutResponse.headers()['location'] ?? '';
        const match = location.match(/\/abbonamento\/(\d+)\/ritorno/);
        expect(match).toBeTruthy();
        const pendingSubscriptionId = match?.[1];

        const webhookResponse = await page.request.post('/mollie/webhook', {
            form: {
                e2e_mock: '1',
                subscription_id: pendingSubscriptionId ?? '',
                status: 'paid',
                sequence_type: 'first',
            },
        });
        expect(webhookResponse.ok()).toBeTruthy();

        await page.goto('/profilo/abbonamento');
        await expect(page.getByText('Attivo', { exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: /gestione abbonamento/i })).toBeVisible();
    });
});
