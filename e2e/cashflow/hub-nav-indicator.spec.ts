import { test, expect } from '@playwright/test';

async function expectIndicatorAlignedWithActiveTab(page: import('@playwright/test').Page, ariaLabel: string): Promise<void> {
    await expect.poll(async () => page.evaluate((label) => {
        const nav = document.querySelector(`nav[aria-label="${label}"]`);

        if (!(nav instanceof HTMLElement)) {
            return false;
        }

        const indicator = nav.querySelector('[data-testid="hub-tab-indicator"]');
        const active = nav.querySelector('[aria-current="page"]');

        if (!(indicator instanceof HTMLElement) || !(active instanceof HTMLElement)) {
            return false;
        }

        const indicatorRect = indicator.getBoundingClientRect();
        const activeRect = active.getBoundingClientRect();

        return Math.abs(indicatorRect.left - activeRect.left) < 3
            && Math.abs(indicatorRect.width - activeRect.width) < 3;
    }, ariaLabel)).toBe(true);
}

test.describe('Hub conti e movimenti', () => {
    test.use({ viewport: { width: 400, height: 838 } });

    test('indicatore allineato su tra nuclei (4° tab)', async ({ page }) => {
        await page.goto('/trasferimenti-tra-nuclei');

        await expect(page.getByRole('navigation', { name: 'Conti e movimenti' })).toBeVisible();
        await expectIndicatorAlignedWithActiveTab(page, 'Conti e movimenti');
    });
});
