import { test, expect } from '@playwright/test';

/**
 * Test E2E — Household
 *
 * Copre: visualizzazione della household corrente, nome, membri,
 * navigazione alle impostazioni.
 */
test.describe('Household', () => {

    /**
     * Naviga alla household attiva tramite la pagina di selezione,
     * senza dipendere dall'ID numerico (che dipende dall'ordine di creazione nel DB).
     */
    async function gotoActiveHousehold(page: import('@playwright/test').Page) {
        await page.goto('/nuclei/seleziona');
        // Clicca su "Casa E2E" per accedere alla household attiva
        await page.getByRole('link', { name: /casa e2e/i }).first().click();
        await expect(page).toHaveURL(/\/nuclei\/\d+/);
    }

    test('carica la pagina della household attiva', async ({ page }) => {
        await gotoActiveHousehold(page);
        await expect(page).toHaveTitle(/household/i);
    });

    test('mostra il nome della household "Casa E2E"', async ({ page }) => {
        await gotoActiveHousehold(page);
        // Il seeder crea "Casa E2E" — .first() evita strict mode se il testo compare più volte
        await expect(page.getByText('Casa E2E').first()).toBeVisible({ timeout: 8_000 });
    });

    test('mostra la sezione dei membri', async ({ page }) => {
        await gotoActiveHousehold(page);
        await expect(
            page.getByText(/membri|membro|partecipanti/i).first()
        ).toBeVisible();
    });

    test('mostra la sezione per invitare nuovi membri', async ({ page }) => {
        await gotoActiveHousehold(page);

        // Click "Invita Membro" per aprire il modale con il campo email
        await page.getByRole('button', { name: /invita membro/i }).click();

        // Il campo email appare nel modale (label: "Email")
        await expect(
            page.getByRole('dialog').getByLabel(/email/i)
                .or(page.locator('#invite_email'))
        ).toBeVisible({ timeout: 8_000 });
    });

    test('il pannello di selezione household è raggiungibile', async ({ page }) => {
        await page.goto('/nuclei/seleziona');
        await expect(page).toHaveURL('/nuclei/seleziona');
    });
});
