import { test, expect } from '@playwright/test';

/**
 * Test E2E — Household
 *
 * Copre: visualizzazione della household corrente, membri, invito.
 * Naviga tramite la pagina di selezione (non usa ID hardcoded).
 */
test.describe('Household', () => {

    async function gotoActiveHousehold(page: import('@playwright/test').Page) {
        await page.goto('/nuclei/seleziona');
        // "Casa E2E" è un dato del seeder — cliccato tramite link che contiene il nome
        await page.getByText('Casa E2E').first().click();
        await expect(page).toHaveURL(/\/nuclei\/\d+/);
    }

    test('carica la pagina della household attiva', async ({ page }) => {
        await gotoActiveHousehold(page);
        await expect(page).toHaveTitle(/household/i);
    });

    test('mostra il nome della household del seeder', async ({ page }) => {
        await gotoActiveHousehold(page);
        await expect(page.getByText('Casa E2E').first()).toBeVisible({ timeout: 8_000 });
    });

    test('mostra la sezione dei membri', async ({ page }) => {
        await gotoActiveHousehold(page);
        await expect(
            page.getByText(/membri|membro|partecipanti/i).first()
        ).toBeVisible();
    });

    test('il pulsante invita membro apre il campo email', async ({ page }) => {
        await gotoActiveHousehold(page);
        await page.getByRole('button', { name: /invita membro/i }).click();
        // Il modale contiene un input email per l'invito (name o type come fallback)
        await expect(
            page.locator('input[name="invite_email"]')
                .or(page.locator('input[type="email"]').last())
        ).toBeVisible({ timeout: 8_000 });
    });

    test('il pannello di selezione household è raggiungibile', async ({ page }) => {
        await page.goto('/nuclei/seleziona');
        await expect(page).toHaveURL('/nuclei/seleziona');
    });
});
