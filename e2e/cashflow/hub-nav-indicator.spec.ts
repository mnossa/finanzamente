import { test, expect } from '@playwright/test';

test.describe('Hub conti e movimenti', () => {
    test.use({ viewport: { width: 400, height: 838 } });

    test('tab attivo movimenti con indicatore visibile', async ({ page }) => {
        await page.goto('/transazioni');

        const hub = page.getByRole('navigation', { name: 'Hub conti e movimenti' });
        await expect(hub).toBeVisible();
        await expect(hub.getByRole('link', { name: 'Movimenti' })).toHaveAttribute('aria-current', 'page');
        await expect(hub.getByTestId('hub-tab-indicator')).toBeVisible();
    });
});
