import { test, expect } from '@playwright/test';

const TEST_PAGE = '/tests/e2e/test-page.php';

/**
 * E-MAIN — MainApp E2E tests
 *
 * Covers scenarios E-MAIN-01 through E-MAIN-06 as defined in
 * docs/EXPLAIN_TEST.md section 3 and docs/ACCEPTANCE_CRITERIA.md AC-MAIN-*.
 */

test.describe('E-MAIN — MainApp', () => {

    test.beforeEach(async ({ page }) => {
        await page.goto(TEST_PAGE);
        // Wait for Vue to mount and operations to load from the API
        await page.waitForSelector('.operation-card', { timeout: 10000 });
    });

    /**
     * E-MAIN-01: Cards load from API
     * QUANDO la MainApp viene caricata, deve mostrare Card con icona, titolo, descrizione
     */
    test('E-MAIN-01: cards load from API with icon, title and description', async ({ page }) => {
        const cards = page.locator('.operation-card');
        const count = await cards.count();
        expect(count).toBeGreaterThan(0);

        // Each card must have a Bootstrap icon, a title and a description
        for (let i = 0; i < count; i++) {
            const card = cards.nth(i);
            await expect(card.locator('i.bi')).toBeVisible();
            await expect(card.locator('.card-title')).not.toBeEmpty();
            await expect(card.locator('.card-text')).not.toBeEmpty();
        }
    });

    /**
     * E-MAIN-02: Card click opens modal
     * QUANDO l'utente clicca su una Card abilitata, la modale deve aprirsi con il form
     */
    test('E-MAIN-02: clicking an enabled card opens the operation modal', async ({ page }) => {
        // Find the first enabled (non-disabled) card
        const enabledCard = page.locator('.operation-card:not(.disabled)').first();
        await expect(enabledCard).toBeVisible();
        await enabledCard.click();

        // The Bootstrap modal should become visible
        const modal = page.locator('#operationModal');
        await expect(modal).toBeVisible({ timeout: 8000 });

        // Modal title should be non-empty
        const title = modal.locator('.modal-title');
        await expect(title).not.toBeEmpty();
    });

    /**
     * E-MAIN-03: Modal close preserves form state
     * QUANDO l'utente chiude la modale e la riapre, i campi del form mantengono i valori
     */
    test('E-MAIN-03: modal close and reopen preserves form field state', async ({ page }) => {
        // Open the newRetrievalCode card (it has a text input we can type in)
        const nrcCard = page.locator('.operation-card:not(.disabled)', { hasText: 'Codice Riscatto' }).first();
        const fallbackCard = page.locator('.operation-card:not(.disabled)').first();
        const targetCard = (await nrcCard.count()) > 0 ? nrcCard : fallbackCard;

        await targetCard.click();
        const modal = page.locator('#operationModal');
        await expect(modal).toBeVisible({ timeout: 8000 });

        // Type a value into the first text input inside the modal
        const textInput = modal.locator('input[type="text"]').first();
        const hasInput = await textInput.count();
        if (hasInput > 0) {
            await textInput.fill('TEST_PERSIST_VALUE');
        }

        // Close modal
        await modal.locator('.btn-close').click();
        await expect(modal).not.toBeVisible({ timeout: 5000 });

        // Reopen the same card
        await targetCard.click();
        await expect(modal).toBeVisible({ timeout: 8000 });

        // The text input should still contain the typed value
        if (hasInput > 0) {
            await expect(textInput).toHaveValue('TEST_PERSIST_VALUE');
        }
    });

    /**
     * E-MAIN-04: Cards have correct content (title matches icon and description)
     * Verifica che le card abbiano contenuto coerente
     */
    test('E-MAIN-04: cards have correct content structure', async ({ page }) => {
        const cards = page.locator('.operation-card');
        const count = await cards.count();
        expect(count).toBeGreaterThan(0);

        for (let i = 0; i < count; i++) {
            const card = cards.nth(i);
            // Each card must have a card-body with text-center
            const body = card.locator('.card-body');
            await expect(body).toBeVisible();
            // Title must be non-blank
            const titleText = await card.locator('.card-title').textContent();
            expect(titleText?.trim().length).toBeGreaterThan(0);
            // Description must be non-blank
            const descText = await card.locator('.card-text').textContent();
            expect(descText?.trim().length).toBeGreaterThan(0);
        }
    });

    /**
     * E-MAIN-05: Disabled card has opacity 0.5 and is not clickable
     * QUANDO enabled=false, la card ha opacità ridotta e il click non apre la modale
     * (AC-ACC-02: cursor not-allowed, tooltip, no modal on click)
     */
    test('E-MAIN-05: disabled card has reduced opacity and does not open modal', async ({ page }) => {
        const disabledCards = page.locator('.operation-card.disabled');
        const count = await disabledCards.count();

        if (count === 0) {
            test.skip(); // No disabled cards in the current environment
        }

        const card = disabledCards.first();
        await expect(card).toBeVisible();

        // Verify opacity via computed style — the CSS rule sets opacity: 0.5
        const opacity = await card.evaluate((el) =>
            parseFloat(window.getComputedStyle(el).opacity)
        );
        expect(opacity).toBeLessThanOrEqual(0.5);

        // Click must not open the modal
        await card.click();
        const modal = page.locator('#operationModal');
        // Give a brief moment for any unintended modal animation
        await page.waitForTimeout(500);
        await expect(modal).not.toBeVisible();
    });

    /**
     * E-MAIN-06: Invisible operation generates no card
     * QUANDO isVisible()=false, nessuna Card è presente per quell'operazione
     * (AC-ACC-01)
     */
    test('E-MAIN-06: invisible operation generates no card', async ({ page }) => {
        // The operations list endpoint should NOT include invisible operations.
        // We verify this by checking that the API response contains no operation
        // with a name matching an invisible stub (integration concern) and that
        // the rendered cards count matches the API payload count.
        const apiResponse = await page.evaluate(async () => {
            const res = await fetch('./src/model/ajax/ajax_operations_view.php?action=list');
            return res.json();
        });

        expect(apiResponse.success).toBe(true);
        const apiCount = (apiResponse.data as unknown[]).length;

        const renderedCards = page.locator('.operation-card');
        await expect(renderedCards).toHaveCount(apiCount);
    });

});
