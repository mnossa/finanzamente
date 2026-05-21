import { test, expect } from '@playwright/test';

test.describe('Simulazioni salvate', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/simulazioni');
    });

    test('utente autenticato vede layout dashboard e può salvare uno scenario', async ({ page }) => {
        await expect(page.getByText('I tuoi scenari salvati')).toBeVisible({ timeout: 10_000 });

        const scenarioName = `E2E Scenario ${Date.now()}`;
        await page.getByLabel('Nome scenario').fill(scenarioName);
        await page.getByRole('button', { name: 'Salva scenario' }).click();

        await expect
            .poll(async () => page.getByLabel(/Apri scenario/i).locator('option', { hasText: scenarioName }).count())
            .toBeGreaterThan(0);

        await page.getByLabel(/Apri scenario/i).selectOption({ label: scenarioName });
        await expect(page.getByLabel('Nome scenario')).toHaveValue(scenarioName);
    });
});
