import { defineConfig, devices } from "@playwright/test";

/**
 * URL base dell'applicazione per i test E2E.
 * In CI viene iniettato tramite variabile d'ambiente PLAYWRIGHT_BASE_URL.
 * In locale l'app gira su porta 8080 (Nginx Docker).
 */
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://localhost:8080";

/**
 * Modalità operativa dell'applicazione sotto test.
 *
 * La variabile deve corrispondere alla configurazione reale del server (.env).
 * I test in e2e/public/modes.spec.ts si attivano/disattivano in base a questo valore.
 *
 * Valori possibili:
 *   normal    (default) — registrazione aperta, nessuna waitlist
 *   prelaunch           — solo il proprietario può registrarsi e accedere
 *   waitlist            — form di iscrizione waitlist Pro visibile
 *
 * Comandi Makefile:
 *   make playwright                                      # modalità normale
 *   make playwright-prelaunch                            # modalità pre-lancio
 *   make playwright-waitlist                             # modalità waitlist
 */
const appMode = process.env.E2E_APP_MODE ?? "normal";

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
