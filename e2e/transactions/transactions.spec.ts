import { test, expect } from '@playwright/test';
import { visibleHrefLocator } from '../helpers';

/**
 * Test E2E — Transazioni
 *
 * Copre: lista transazioni, filtri, navigazione al form di creazione.
 */
test.describe('Transazioni', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/transazioni');
    });

    test('la pagina transazioni si carica', async ({ page }) => {
        await expect(page).toHaveURL('/transazioni');
        await expect(page).toHaveTitle(/transazioni/i);
    });

    test('esiste il link per creare una nuova transazione', async ({ page }) => {
        await expect(visibleHrefLocator(page, '/transazioni/crea')).toBeVisible();
    });

    test('il link nuova transazione porta al form', async ({ page }) => {
        await visibleHrefLocator(page, '/transazioni/crea').click();
        await expect(page).toHaveURL('/transazioni/crea');
        await expect(page).toHaveTitle(/nuova transazione/i);
    });

    test('la lista mostra stato vuoto o righe', async ({ page }) => {
        // Stato vuoto oppure righe linkate a /transazioni/:id
        const emptyState = page.getByText(/nessuna transazione trovata/i).first();
        // Le righe sono link verso /transazioni/:id (tutta la riga è linkabile su mobile)
        const rowLinks = page.locator('a[href*="/transazioni/"]').filter({ hasNot: page.locator('[href="/transazioni"]') });

        await expect
            .poll(async () => {
                const emptyVisible = await emptyState.isVisible().catch(() => false);
                const rowsCount = await rowLinks.count();
                return emptyVisible || rowsCount > 0;
            }, { timeout: 15_000 })
            .toBeTruthy();
    });

    test('i filtri di ricerca sono presenti', async ({ page }) => {
        // Il pannello filtri è collassabile: verifichiamo che esista il trigger (sempre visibile)
        // oppure che i combobox siano direttamente visibili se il pannello è già aperto
        const filterTrigger = page.getByTestId('filter-summary');
        const combobox = page.getByRole('combobox').first();

        await expect
            .poll(async () => {
                const triggerVisible = await filterTrigger.isVisible().catch(() => false);
                const comboboxVisible = await combobox.isVisible().catch(() => false);
                return triggerVisible || comboboxVisible;
            }, { timeout: 8_000 })
            .toBeTruthy();
    });

    test('esporta scarica un file CSV', async ({ page }) => {
        const exportLink = page.getByRole('link', { name: 'Esporta' }).filter({ visible: true });
        await expect(exportLink).toBeVisible();
        const downloadPromise = page.waitForEvent('download');
        await exportLink.click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toMatch(/^transazioni-\d{4}-\d{2}-\d{2}_.*\.csv$/);
    });

    test('i filtri si applicano solo dopo la CTA Applica filtri', async ({ page }) => {
        await page.getByTestId('filter-summary').click();
        await expect(page.getByLabel('Cerca nella descrizione')).toBeVisible({ timeout: 8_000 });

        const applyButton = page.getByTestId('apply-filters').filter({ visible: true }).first();
        await expect(applyButton).toBeVisible();
        await expect(applyButton).toBeDisabled();

        const descriptionInput = page.getByLabel('Cerca nella descrizione');
        const marker = `e2e-filtro-${Date.now()}`;
        await descriptionInput.fill(marker);

        await expect(applyButton).toBeEnabled();
        await expect(page).not.toHaveURL(new RegExp(`description=${encodeURIComponent(marker)}`));

        await applyButton.click();
        await expect(page).toHaveURL(new RegExp(`description=${encodeURIComponent(marker)}`));
    });

    test('checkbox regex descrizione è presente nel pannello filtri', async ({ page }) => {
        await page.getByTestId('filter-summary').click();
        await expect(page.getByLabel('Cerca nella descrizione')).toBeVisible({ timeout: 8_000 });
        await expect(page.getByLabel('Usa espressione regolare')).toBeVisible();
    });

    test('sezione Prossimi movimenti collassa ed espande', async ({ page }) => {
        await page.evaluate(() => {
            Object.keys(localStorage)
                .filter((k) => k.startsWith('finanzamente.upcomingMovements.expanded'))
                .forEach((k) => localStorage.removeItem(k));
        });
        await page.reload();

        const toggle = page.getByTestId('upcoming-movements-toggle');
        await expect(toggle).toBeVisible({ timeout: 10_000 });
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(page.locator('#upcoming-movements-list')).toHaveCount(0);

        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(page.locator('#upcoming-movements-list')).toBeVisible();

        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(page.locator('#upcoming-movements-list')).toHaveCount(0);
    });

    test('navigazione paginazione funziona se ci sono più pagine', async ({ page }) => {
        const pagination = page.getByRole('navigation', { name: /paginazione/i });
        if (await pagination.isVisible()) {
            const nextLink = pagination.locator('a[href*="page=2"]');
            if (await nextLink.isVisible()) {
                await nextLink.click();
                await expect(page).toHaveURL(/page=2/);
            }
        }
    });

    test('il form di creazione espone il pagamento su più conti', async ({ page }) => {
        await visibleHrefLocator(page, '/transazioni/crea').click();
        await expect(page).toHaveURL('/transazioni/crea');

        // Split nascosto su conti buoni pasto (WFI-109): seleziona un conto corrente/carta.
        const accountSelect = page.locator('#account_id');
        await expect(accountSelect).toBeVisible({ timeout: 8_000 });
        const options = accountSelect.locator('option');
        const count = await options.count();
        let selected = false;
        for (let i = 0; i < count; i++) {
            const label = (await options.nth(i).textContent())?.trim() ?? '';
            const value = await options.nth(i).getAttribute('value');
            if (!value || /^buoni\b/i.test(label)) {
                continue;
            }
            await accountSelect.selectOption(value);
            selected = true;
            break;
        }
        expect(selected).toBeTruthy();

        await expect(page.getByText(/pagamento su più conti/i)).toBeVisible({ timeout: 8_000 });
    });

    test('il form di creazione espone il toggle "valuta diversa dal conto"', async ({ page }) => {
        await visibleHrefLocator(page, '/transazioni/crea').click();
        await expect(page).toHaveURL('/transazioni/crea');

        // Le opzioni aggiuntive (incluso FX) sono in un pannello collassabile — apriamo prima
        const extraDetails = page.locator('details').filter({ hasText: /opzioni aggiuntive/i });
        if (await extraDetails.count() > 0) {
            const isOpen = await extraDetails.getAttribute('open');
            if (isOpen === null) {
                await extraDetails.locator('summary').click();
            }
        }

        const toggle = page.getByRole('button', { name: /valuta diversa.*conto|pagato in valuta/i });
        await expect(toggle).toBeVisible({ timeout: 5_000 });

        // Apre la sezione FX e verifica che compaiano i tre campi di valuta
        await toggle.click();
        await expect(page.getByLabel(/importo originale/i)).toBeVisible();
        await expect(page.getByLabel(/valuta originale|valuta/i).first()).toBeVisible();
        await expect(page.getByLabel(/cambio manuale/i)).toBeVisible();
    });

    test('mostra l\'anteprima del cambio quando si seleziona una valuta diversa dal conto', async ({ page }) => {
        await visibleHrefLocator(page, '/transazioni/crea').click();
        await expect(page).toHaveURL('/transazioni/crea');

        // Apri opzioni aggiuntive se chiuse
        const extraDetails = page.locator('details').filter({ hasText: /opzioni aggiuntive/i });
        if (await extraDetails.count() > 0) {
            const isOpen = await extraDetails.getAttribute('open');
            if (isOpen === null) {
                await extraDetails.locator('summary').click();
            }
        }

        await page.getByRole('button', { name: /valuta diversa.*conto|pagato in valuta/i }).click();
        // Default è la valuta dell'utente: forziamo GBP per garantire mismatch col conto principale (EUR)
        await page.getByLabel(/valuta originale|valuta/i).first().selectOption('GBP');

        // L'hint compare (eventualmente con stato "Calcolo cambio…" che si stabilizza)
        const hint = page.getByTestId('fx-preview-hint');
        await expect(hint).toBeVisible({ timeout: 5_000 });
        await expect(hint).toContainText(/cambio del giorno|calcolo cambio|=/i, { timeout: 10_000 });
    });

    test('il conto in valuta estera "Revolut GBP" è visibile nella select del form', async ({ page }) => {
        await visibleHrefLocator(page, '/transazioni/crea').click();
        await expect(page).toHaveURL('/transazioni/crea');

        const accountSelect = page.locator('#account_id');
        await expect(accountSelect).toBeVisible();

        // L'opzione esiste solo se il seeder E2E ha creato il conto Revolut GBP.
        // Verifichiamo che il name dell'opzione contenga "Revolut" e mostri (GBP) come valuta.
        await expect(accountSelect.locator('option', { hasText: /revolut/i })).toContainText(/gbp/i);
    });

    test('su mobile il link Importa è nel corpo pagina (toolbar sotto header)', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/transazioni');
        const importLink = visibleHrefLocator(page, '/transazioni/importa');
        await importLink.scrollIntoViewIfNeeded();
        await expect(importLink).toBeVisible();
        await importLink.click();
        await expect(page).toHaveURL(/\/transazioni\/importa/);
    });

    test('su mobile evidenzia le transazioni generate da PAC', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/transazioni');

        await expect(page.getByLabel('Generata da PAC', { exact: true }).first()).toBeVisible({ timeout: 15_000 });
    });

    test('su desktop distingue transazioni PAC e ricorrenti', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto('/transazioni');

        const desktopRows = page.locator('div.hidden.sm\\:grid');

        await expect(
            desktopRows.getByRole('link', { name: /Abbonamento E2E ricorrente, generata da ricorrenza/i }),
        ).toBeVisible({ timeout: 15_000 });
        await expect(desktopRows.getByLabel('Generata da ricorrenza', { exact: true })).toBeVisible();
        await expect(desktopRows.getByLabel('Generata da PAC', { exact: true })).toBeVisible();
    });

    test('il form creazione transazione espone solo il flusso manuale', async ({ page }) => {
        await page.goto('/transazioni/crea');

        await expect(page).toHaveURL('/transazioni/crea');
        await expect(page.getByRole('heading', { name: 'Inserimento rapido' })).toHaveCount(0);
        await expect(page.locator('[data-testid^="quick-chip-"]')).toHaveCount(0);
        await expect(page.locator('#amount')).toBeVisible();
        await expect(page.locator('#date')).toBeVisible();
        await expect(page.locator('#account_id')).toBeVisible();
        await expect(page.getByRole('button', { name: /salva transazione|salva/i }).first()).toBeVisible();
    });

    test('dettaglio: slide-over su desktop, pagina completa su mobile', async ({ page }, testInfo) => {
        const isMobile = testInfo.project.name === 'autenticato-mobile';

        const emptyState = page.getByText(/nessuna transazione trovata/i).first();
        const hasEmpty = await emptyState.isVisible().catch(() => false);
        test.skip(hasEmpty, 'Serve almeno una transazione in lista');

        if (isMobile) {
            const mobileLink = page
                .locator('div.sm\\:hidden a[href*="/transazioni/"]')
                .filter({ visible: true })
                .first();
            await expect(mobileLink).toBeVisible({ timeout: 15_000 });
            await mobileLink.click();
            await expect(page).toHaveURL(/\/transazioni\/\d+/);
            await expect(page).toHaveTitle(/dettaglio transazione/i);
            return;
        }

        const desktopTitleLink = page
            .locator('div.hidden.sm\\:grid a.min-w-0[href*="/transazioni/"]')
            .filter({ visible: true })
            .first();
        await expect(desktopTitleLink).toBeVisible({ timeout: 15_000 });
        await desktopTitleLink.click();

        await expect(page).toHaveURL(/\/transazioni(?:\?|$)/);
        const slideOver = page.getByTestId('transaction-slide-over');
        await expect(slideOver).toBeVisible({ timeout: 10_000 });
        await expect(page.getByTestId('transaction-slide-over-close')).toBeVisible();
        await expect(slideOver.getByText(/dettaglio senza lasciare/i)).toBeVisible();

        await page.getByTestId('transaction-slide-over-close').click();
        await expect(slideOver).toBeHidden({ timeout: 5_000 });
    });
});
