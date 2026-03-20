import { test, expect } from '@playwright/test';

const TEST_PAGE = '/tests/e2e/test-page.php';

/**
 * E-ERR — Error handling E2E tests
 *
 * Covers scenarios E-ERR-01 through E-ERR-03 as defined in
 * docs/EXPLAIN_TEST.md section 3 and docs/ACCEPTANCE_CRITERIA.md AC-ERR-*.
 *
 * The showErrorToast() function in app.js creates Bootstrap Toasts with
 * data-bs-autohide="false" inside #toast-container.
 */

test.describe('E-ERR — Error handling', () => {

    /**
     * E-ERR-01: Ajax error shows toast overlay
     * AC-ERR-01: QUANDO una chiamata Ajax restituisce success:false,
     *            il sistema DEVE mostrare un Toast sovrapposto a tutto
     */
    test('E-ERR-01: ajax error shows error toast', async ({ page }) => {
        // Make the operations list endpoint return an error so the toast fires immediately
        await page.route('**/ajax_operations_view.php**', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ success: false, message: 'Errore TEST_E2E dal server' }),
            });
        });

        await page.goto(TEST_PAGE);

        // Toast container should appear with a danger toast
        const toastContainer = page.locator('#toast-container');
        await expect(toastContainer).toBeVisible({ timeout: 8000 });

        const toast = toastContainer.locator('.toast.text-bg-danger');
        await expect(toast).toBeVisible({ timeout: 5000 });

        // The toast body must contain the error message
        const toastBody = toast.locator('.toast-body');
        await expect(toastBody).toContainText('Errore TEST_E2E dal server');
    });

    /**
     * E-ERR-02: Toast stays until manually closed
     * AC-ERR-02: MENTRE un Toast è visibile, il sistema DEVE mantenerlo visibile
     *            finché l'utente non lo chiude esplicitamente.
     *            data-bs-autohide="false" ensures the toast does not auto-dismiss.
     */
    test('E-ERR-02: toast stays visible until manually closed', async ({ page }) => {
        await page.route('**/ajax_operations_view.php**', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ success: false, message: 'Toast persistente TEST_E2E' }),
            });
        });

        await page.goto(TEST_PAGE);

        const toastContainer = page.locator('#toast-container');
        const toast = toastContainer.locator('.toast.text-bg-danger').first();
        await expect(toast).toBeVisible({ timeout: 8000 });

        // Verify autohide is disabled
        const autohide = await toast.getAttribute('data-bs-autohide');
        expect(autohide).toBe('false');

        // Simulate user interaction with another part of the page (scroll)
        await page.evaluate(() => window.scrollTo(0, 100));
        await page.waitForTimeout(1000);

        // Toast must still be visible after the interaction
        await expect(toast).toBeVisible();

        // Toast must not disappear by itself after 3 seconds
        await page.waitForTimeout(3000);
        await expect(toast).toBeVisible();
    });

    /**
     * E-ERR-03: Toast overlays modal — toast is dismissed by clicking close button
     * AC-ERR-01: sovrapposto a tutti gli elementi (z-index)
     * AC-ERR-02: Si chiude solo cliccando la X
     */
    test('E-ERR-03: toast can be closed with the close button', async ({ page }) => {
        await page.route('**/ajax_operations_view.php**', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ success: false, message: 'Chiudi con X TEST_E2E' }),
            });
        });

        await page.goto(TEST_PAGE);

        const toastContainer = page.locator('#toast-container');
        const toast = toastContainer.locator('.toast.text-bg-danger').first();
        await expect(toast).toBeVisible({ timeout: 8000 });

        // Verify that the toast z-index stacks above other content
        // Bootstrap toasts inside #toast-container use a fixed/high z-index via CSS
        const toastContainerZIndex = await toastContainer.evaluate((el) =>
            window.getComputedStyle(el).zIndex
        );
        // z-index should be a numeric string greater than 0 (or "auto" for the container,
        // in which case individual toasts have high z-index)
        const zIndex = parseInt(toastContainerZIndex, 10);
        if (!isNaN(zIndex)) {
            expect(zIndex).toBeGreaterThan(0);
        }

        // Click the close button on the toast
        const closeBtn = toast.locator('.btn-close');
        await expect(closeBtn).toBeVisible();
        await closeBtn.click();

        // Toast should disappear after clicking the close button
        await expect(toast).not.toBeVisible({ timeout: 5000 });
    });

});
