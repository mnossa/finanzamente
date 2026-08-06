/**
 * Screenshot capture for README (demo user).
 * Usage (stack up + make demo-data):
 *   node scripts/capture-readme-screenshots.mjs
 */
import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const outDir = path.join(root, 'docs', 'screenshots');
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8080';

/** @type {{ name: string, path: string, width: number, height: number }[]} */
const shots = [
  { name: 'dashboard-desktop', path: '/dashboard', width: 1440, height: 900 },
  { name: 'transactions-desktop', path: '/transazioni', width: 1440, height: 900 },
  { name: 'accounts-desktop', path: '/conti', width: 1440, height: 900 },
  { name: 'budgets-desktop', path: '/budget', width: 1440, height: 900 },
  { name: 'goals-desktop', path: '/obiettivi-finanziari', width: 1440, height: 900 },
  { name: 'debts-desktop', path: '/debiti-crediti', width: 1440, height: 900 },
  { name: 'patrimonio-desktop', path: '/patrimonio', width: 1440, height: 900 },
  { name: 'investments-desktop', path: '/investimenti', width: 1440, height: 900 },
  { name: 'categories-desktop', path: '/categorie', width: 1440, height: 900 },
  { name: 'formula-widgets-desktop', path: '/widget-formule', width: 1440, height: 900 },
  { name: 'dashboard-mobile', path: '/dashboard', width: 390, height: 844 },
  { name: 'transactions-mobile', path: '/transazioni', width: 390, height: 844 },
];

async function login(page) {
  await page.goto(`${baseURL}/accedi`, { waitUntil: 'networkidle' });
  await page.locator('input[type="email"]').fill('mario.rossi@example.com');
  await page.locator('input[type="password"]').fill('password');
  await page.locator('form').locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.includes('/accedi'), { timeout: 30_000 });
}

async function dismissNoise(page) {
  for (const name of [/accetta/i, /ok/i, /chiudi/i, /ho capito/i]) {
    const btn = page.getByRole('button', { name }).first();
    if (await btn.isVisible().catch(() => false)) {
      await btn.click().catch(() => {});
    }
  }
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    locale: 'it-IT',
    timezoneId: 'Europe/Rome',
    colorScheme: 'light',
  });
  const page = await context.newPage();

  await login(page);
  await dismissNoise(page);

  if (!page.url().includes('/dashboard')) {
    await page.goto(`${baseURL}/dashboard`, { waitUntil: 'networkidle' }).catch(() => {});
  }

  for (const shot of shots) {
    await page.setViewportSize({ width: shot.width, height: shot.height });
    const response = await page.goto(`${baseURL}${shot.path}`, { waitUntil: 'networkidle' });
    await dismissNoise(page);
    await page.waitForTimeout(1200);
    const file = path.join(outDir, `${shot.name}.png`);
    await page.screenshot({ path: file, fullPage: false });
    const status = response?.status() ?? '?';
    console.log(`wrote ${shot.name}.png status=${status} url=${page.url()}`);
  }

  await browser.close();
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
