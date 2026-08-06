import { defineConfig, devices } from "@playwright/test";

/**
 * Base URL E2E. In CI: PLAYWRIGHT_BASE_URL. Locale tipico: porta 8081 (app_e2e).
 */
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://localhost:8080";

export default defineConfig({
  testDir: "./e2e",

  /* Esecuzione sequenziale: i test condividono il medesimo DB E2E */
  fullyParallel: false,
  workers: 1,

  /* Blocca l'uso di .only in CI */
  forbidOnly: !!process.env.CI,

  /**
   * Retry policy:
   *   - 1 retry in CI (abbastanza per isolare flakiness occasionale, senza sprecare tempo)
   *   - 0 retry in locale (fallimento immediato = feedback veloce durante sviluppo)
   */
  retries: process.env.CI ? 1 : 0,

  /** Timeout per singolo test: 30 secondi (include attese di rete e render) */
  timeout: 30_000,

  /** Timeout per singola asserzione expect() */
  expect: {
    timeout: 6_000,
  },

  reporter: [["html", { open: "never" }], ["list"]],

  use: {
    baseURL,
    trace: "on-first-retry",
    screenshot: "only-on-failure",
    video: "retain-on-failure",
    locale: "it-IT",
    timezoneId: "Europe/Rome",
    /** Timeout azioni (click, fill, ecc.) */
    actionTimeout: 10_000,
    /** Timeout navigazione (goto, waitForURL, ecc.) */
    navigationTimeout: 20_000,
  },

  projects: [
    {
      name: "setup",
      testMatch: /auth\.setup\.ts/,
    },
    {
      name: "pubblico",
      testMatch: ["**/public/**/*.spec.ts", "**/auth/**/*.spec.ts"],
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "autenticato-desktop",
      testIgnore: ["**/public/**/*.spec.ts", "**/auth/**/*.spec.ts"],
      dependencies: ["setup"],
      use: {
        ...devices["Desktop Chrome"],
        storageState: "e2e/.auth/user.json",
      },
    },
    {
      name: "autenticato-mobile",
      testIgnore: ["**/public/**/*.spec.ts", "**/auth/**/*.spec.ts"],
      dependencies: ["setup"],
      use: {
        ...devices["Pixel 5"],
        storageState: "e2e/.auth/user.json",
      },
    },
  ],
});
