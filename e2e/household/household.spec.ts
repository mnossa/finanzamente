import { test, expect } from '@playwright/test';

/**
 * Test E2E — Household
 *
 * Copre: visualizzazione della household corrente, nome, membri,
 * navigazione alle impostazioni.
 */
test.describe('Household', () => {
    test('carica la pagina della household attiva', async ({ page }) => {
        await page.goto('/households/1');

        // La pagina può reindirizzare all'ID corretto
        await expect(page).toHaveURL(/\/households\/\d+/);
        await expect(page).toHaveTitle(/household/i);
    });

    test('mostra il nome della household "Casa E2E"', async ({ page }) => {
        await page.goto('/households/1');

        // Il seeder crea "Casa E2E" — .first() evita strict mode se il testo compare più volte
        await expect(page.getByText('Casa E2E').first()).toBeVisible({ timeout: 8_000 });
    });

    test('mostra la sezione dei membri', async ({ page }) => {
        await page.goto('/households/1');

        // La pagina deve avere una sezione con i membri
        await expect(
            page.getByText(/membri|membro|partecipanti/i).first()
        ).toBeVisible();
    });

    test('mostra la sezione per invitare nuovi membri', async ({ page }) => {
        await page.goto('/households/1');

        // Click "Invita Membro" per aprire il modale con il campo email
        await page.getByRole('button', { name: /invita membro/i }).click();

        // Il campo email appare nel modale
        await expect(page.locator('#invite_email')).toBeVisible({ timeout: 8_000 });
    });

    test('il pannello di selezione household è raggiungibile', async ({ page }) => {
        await page.goto('/nuclei/seleziona');
        await expect(page).toHaveURL('/households/select');
    });
});
