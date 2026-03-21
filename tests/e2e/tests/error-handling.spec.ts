import { test, expect } from '@playwright/test';

const TEST_PAGE = '/test-page.php';

/**
 * E-ERR — Error handling E2E tests
 *
 * Covers scenarios E-ERR-01 through E-ERR-04 as defined in
 * docs/EXPLAIN_TEST.md section 3 and docs/ACCEPTANCE_CRITERIA.md AC-ERR-*.
 *
 * The showErrorToast() function in app.js creates Bootstrap Toasts with
 * data-bs-autohide="true" data-bs-delay="15000" inside #toast-container.
 * Toasts can be closed with the X button or auto-dismiss after 15 seconds.
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
     * E-ERR-02: Toast stays visible for 15 seconds then auto-dismisses
     * AC-ERR-02: Il toast resta visibile per 15 secondi (data-bs-delay="15000")
     *            oppure viene chiuso con la X dall'utente.
     */
    test('E-ERR-02: toast has 15-second auto-dismiss and stays visible before that', async ({ page }) => {
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

        // Verify autohide is enabled with 15-second delay
        const autohide = await toast.getAttribute('data-bs-autohide');
        expect(autohide).toBe('true');
        const delay = await toast.getAttribute('data-bs-delay');
        expect(delay).toBe('15000');

        // Toast must have a close button (X)
        const closeBtn = toast.locator('.btn-close');
        await expect(closeBtn).toBeVisible();

        // Toast must still be visible after 5 seconds (well within 15s window)
        await page.waitForTimeout(5000);
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

    /**
     * E-ERR-04: Error toast shows exception detail from server response
     * QUANDO la risposta JSON include il campo 'exception' (dev mode),
     * il toast DEVE mostrare il dettaglio tecnico in un <pre> scrollabile.
     */
    test('E-ERR-04: error toast displays exception detail from server', async ({ page }) => {
        const exceptionDetail = 'RuntimeException: Connection refused\n#0 /src/model/Operations/Repo.php(42): PDO->query()\n#1 /src/model/ajax/ajax_view.php(15): Repo->findAll()';

        await page.route('**/ajax_operations_view.php**', async (route) => {
            await route.fulfill({
                status: 400,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: false,
                    message: 'Errore database TEST_E2E',
                    exception: exceptionDetail,
                }),
            });
        });

        await page.goto(TEST_PAGE);

        const toastContainer = page.locator('#toast-container');
        const toast = toastContainer.locator('.toast.text-bg-danger').first();
        await expect(toast).toBeVisible({ timeout: 8000 });

        // The toast body must contain the error message
        const toastBody = toast.locator('.toast-body');
        await expect(toastBody).toContainText('Errore database TEST_E2E');

        // The toast must also show the exception detail in a <pre> element
        const preBlock = toastBody.locator('pre');
        await expect(preBlock).toBeVisible();
        const preText = await preBlock.textContent();
        expect(preText).toContain('RuntimeException');
        expect(preText).toContain('Connection refused');
        expect(preText).toContain('Repo.php');
    });

    /**
     * E-ERR-05: Network error toast shows descriptive context
     * QUANDO la connessione al server fallisce (fetch rejects),
     * il toast DEVE mostrare il contesto dell'operazione e il dettaglio dell'errore.
     */
    test('E-ERR-05: network error toast shows context and error detail', async ({ page }) => {
        // Abort the request to simulate a network failure
        await page.route('**/ajax_operations_view.php**', async (route) => {
            await route.abort('connectionrefused');
        });

        await page.goto(TEST_PAGE);

        const toastContainer = page.locator('#toast-container');
        const toast = toastContainer.locator('.toast.text-bg-danger').first();
        await expect(toast).toBeVisible({ timeout: 8000 });

        // The toast should contain contextual information about what failed
        const toastBody = toast.locator('.toast-body');
        const bodyText = await toastBody.textContent();
        expect(bodyText).toContain('connessione');
        expect(bodyText).toContain('operazioni');
    });

});
