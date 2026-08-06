import { test, expect } from '@playwright/test';

test.describe('Hub Movimenti e Organizzazione', () => {
    test('transazioni espone hub Movimenti', async ({ page }) => {
        await page.goto('/transazioni');
        const hub = page.getByRole('navigation', { name: 'Movimenti' });
        await expect(hub).toBeVisible();
        await expect(hub.getByRole('link', { name: /movimenti|transazioni/i })).toHaveAttribute('aria-current', 'page');
        await expect(hub.getByRole('link', { name: /trasferim/i })).toBeVisible();
        await expect(hub.getByRole('link', { name: /rimborsi/i })).toBeVisible();
        await expect(hub.getByRole('link', { name: /ricorr/i })).toBeVisible();
    });

    test('organizzazione ha Conti, Categorie ed Etichette', async ({ page }) => {
        await page.goto('/conti');
        const hub = page.getByRole('navigation', { name: 'Organizzazione' });
        await expect(hub).toBeVisible();
        await expect(hub.getByRole('link', { name: /^conti$/i })).toHaveAttribute('aria-current', 'page');
        await expect(hub.getByRole('link', { name: /categorie/i })).toBeVisible();
        await expect(hub.getByRole('link', { name: /etichette/i })).toBeVisible();

        await hub.getByRole('link', { name: /etichette/i }).click();
        await expect(page).toHaveURL(/\/etichette/);
        await expect(hub.getByRole('link', { name: /etichette/i })).toHaveAttribute('aria-current', 'page');
    });
});
