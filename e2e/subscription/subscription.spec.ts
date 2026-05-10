import { test, expect } from '@playwright/test';

test.describe('Abbonamento', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/profilo/abbonamento');
    });

    test('la pagina abbonamento si carica', async ({ page }) => {
        await expect(page).toHaveURL('/profilo/abbonamento');
        await expect(page).toHaveTitle(/abbonamento/i);
    });

    test('mostra informazioni sul piano corrente', async ({ page }) => {
        // Testa la struttura: heading "piano attuale" presente
        await expect(page.getByRole('heading', { name: /piano attuale/i })).toBeVisible();
    });

    test('apre e salva form dati fatturazione', async ({ page }) => {
        await page.getByRole('button', { name: /modifica/i }).click();

        // Campi strutturali del form fatturazione
        await expect(page.locator('input[name="billing_name"]')).toBeVisible();
        await expect(page.locator('input[name="billing_email"]')).toBeVisible();

        await page.locator('input[name="billing_name"]').fill('Utente E2E Billing');
        await page.locator('input[name="billing_email"]').fill('e2e@finanzamente.test');

        await page.locator('form').filter({ has: page.locator('input[name="billing_name"]') })
            .locator('[type="submit"]').click();

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
        // Verifica funzionale: heading "gestione abbonamento" compare solo per abbonati attivi
        await expect(page.getByRole('heading', { name: /gestione abbonamento/i })).toBeVisible();
    });
});
