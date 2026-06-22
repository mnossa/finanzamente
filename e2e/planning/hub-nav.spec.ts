import { test, expect } from '@playwright/test';
import { mobileFabLinkLocator } from '../helpers';

/**
 * Hub Pianificazione — navigazione tab e assenza CTA duplicate su mobile.
 */
test.describe('Hub pianificazione', () => {
    test('su mobile debiti/crediti non mostra toolbar Nuovo duplicata', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/debiti-crediti');
        await expect(mobileFabLinkLocator(page)).toHaveAttribute('href', /\/debiti-crediti\/crea/);
        await expect(page.locator('div.mb-3.lg\\:hidden a[href*="/debiti-crediti/crea"]')).toHaveCount(0);
    });

    test('hub nav usa tab con underline (tab attivo evidenziato)', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/debiti-crediti');
        const activeTab = page.getByRole('navigation', { name: 'Pianificazione e risparmio' }).getByRole('link', { name: /debiti/i });
        await expect(activeTab).toHaveAttribute('aria-current', 'page');
        await expect(activeTab).toHaveClass(/border-emerald/);
    });

    test('swipe orizzontale su mobile naviga al tab hub adiacente', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/debiti-crediti');

        const main = page.locator('main').first();
        const box = await main.boundingBox();
        expect(box).not.toBeNull();

        const startX = box!.x + box!.width * 0.85;
        const endX = box!.x + box!.width * 0.15;
        const y = box!.y + Math.min(box!.height * 0.45, 420);

        const cdp = await page.context().newCDPSession(page);
        await cdp.send('Input.dispatchTouchEvent', {
            type: 'touchStart',
            touchPoints: [{ x: startX, y }],
        });
        await cdp.send('Input.dispatchTouchEvent', {
            type: 'touchEnd',
            touchPoints: [{ x: endX, y }],
        });

        await expect(page).toHaveURL(/\/obiettivi-finanziari/, { timeout: 10_000 });
    });
});
