import { test, expect } from '@playwright/test';

/**
 * Regression guard per Core Web Vitals sulla dashboard con lazy load widget formula.
 * CLS: skeleton a altezza riservata + payload prioritari SSR per above-the-fold.
 * TBT: long task budget dopo defer dnd-kit / Recharts.
 */
test.describe('Dashboard — Core Web Vitals (lazy load widget formula)', () => {
    test('accumula poco layout shift durante il caricamento dei widget formula', async ({ page }) => {
        await page.goto('/dashboard');

        const cls = await page.evaluate(async () => {
            let clsValue = 0;

            const observer = new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    const layoutShift = entry as PerformanceEntry & { value?: number; hadRecentInput?: boolean };
                    if (!layoutShift.hadRecentInput && typeof layoutShift.value === 'number') {
                        clsValue += layoutShift.value;
                    }
                }
            });

            observer.observe({ type: 'layout-shift', buffered: true });

            await new Promise((resolve) => window.setTimeout(resolve, 4500));

            observer.disconnect();

            return clsValue;
        });

        expect(cls).toBeLessThan(0.1);
    });

    test('il titolo dashboard è visibile prima del completamento del fetch payload', async ({ page }) => {
        await page.route('**/dashboard/formula-widget-payloads', async (route) => {
            await new Promise((resolve) => window.setTimeout(resolve, 2500));
            await route.continue();
        });

        await page.goto('/dashboard');

        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible({ timeout: 5000 });
    });

    test('accumula pochi long task durante il boot della dashboard', async ({ page }) => {
        await page.goto('/dashboard');

        const longTaskStats = await page.evaluate(async () => {
            const tasks: number[] = [];

            const observer = new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    tasks.push(entry.duration);
                }
            });

            observer.observe({ type: 'longtask', buffered: true });

            await new Promise((resolve) => window.setTimeout(resolve, 5000));

            observer.disconnect();

            const totalBlocking = tasks.reduce((sum, duration) => sum + Math.max(0, duration - 50), 0);

            return {
                count: tasks.length,
                totalBlockingMs: totalBlocking,
            };
        });

        expect(longTaskStats.count).toBeLessThan(25);
        expect(longTaskStats.totalBlockingMs).toBeLessThan(2500);
    });
});
