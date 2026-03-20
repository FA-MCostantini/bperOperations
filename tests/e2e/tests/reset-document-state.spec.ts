import { test, expect } from '@playwright/test';

const TEST_PAGE = '/tests/e2e/test-page.php';

/**
 * E-RDS — resetDocumentState E2E tests
 *
 * Covers scenarios E-RDS-01 through E-RDS-04 as defined in
 * docs/EXPLAIN_TEST.md section 3 and docs/ACCEPTANCE_CRITERIA.md AC-RDS-*.
 */

/**
 * Mock data: one PENDING row (Doc. PENDING > 0) and one ERROR row (Doc. PENDING = 0).
 * The resetDocumentState component uses isPending(row) => row['Doc. PENDING'] > 0.
 */
const MOCK_RDS_DATA = [
    { id: 1, 'Draft ID': 'DRAFT_PENDING_1', 'Doc. PENDING': 2, 'Doc. ERROR': 0 },
    { id: 2, 'Draft ID': 'DRAFT_ERROR_1', 'Doc. PENDING': 0, 'Doc. ERROR': 3 },
];

/** Open the ResetDocumentState modal and wait for the table to appear. */
async function openRdsModal(page: import('@playwright/test').Page) {
    await page.goto(TEST_PAGE);
    await page.waitForSelector('.operation-card', { timeout: 10000 });

    const rdsCard = page.locator('.operation-card:not(.disabled)', { hasText: 'Stato Documento' });
    const fallback = page.locator('.operation-card:not(.disabled)').nth(2);
    const card = (await rdsCard.count()) > 0 ? rdsCard.first() : fallback;
    await card.click();

    const modal = page.locator('#operationModal');
    await expect(modal).toBeVisible({ timeout: 10000 });

    await page.waitForSelector('#operationModal .table', { timeout: 8000 });
    return modal;
}

test.describe('E-RDS — resetDocumentState', () => {

    /**
     * E-RDS-01: Table loads with PENDING/ERROR counts differentiated visually
     * AC-RDS-01: la tabella mostra i conteggi corretti
     * AC-RDS-02: PENDING sfondo bianco icona orologio; ERROR sfondo grigio icona X
     */
    test('E-RDS-01: table loads with PENDING and ERROR rows visually differentiated', async ({ page }) => {
        await page.route('**/ajax_resetDocumentState_view.php**', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(MOCK_RDS_DATA),
            });
        });

        const modal = await openRdsModal(page);

        // There should be exactly 2 data rows
        const bodyRows = modal.locator('.table tbody tr');
        await expect(bodyRows).toHaveCount(2);

        // PENDING row (index 0): should have the clock icon and white background
        const pendingRow = bodyRows.nth(0);
        const clockIcon = pendingRow.locator('.bi-clock-history');
        await expect(clockIcon).toBeVisible();

        // Verify PENDING row has no grey background class (bg-light)
        const pendingClasses = await pendingRow.getAttribute('class');
        expect(pendingClasses).not.toContain('bg-light');

        // ERROR row (index 1): should have the X icon and grey (bg-light) class
        const errorRow = bodyRows.nth(1);
        const xIcon = errorRow.locator('.bi-x-circle-fill');
        await expect(xIcon).toBeVisible();

        const errorClasses = await errorRow.getAttribute('class');
        expect(errorClasses).toContain('bg-light');
    });

    /**
     * E-RDS-02: PENDING rows have clock icon that is clickable and opens confirm modal
     * AC-RDS-03: QUANDO l'utente clicca sull'icona di una riga PENDING, appare la modale di conferma
     */
    test('E-RDS-02: clicking clock icon on PENDING row opens confirmation modal', async ({ page }) => {
        await page.route('**/ajax_resetDocumentState_view.php**', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(MOCK_RDS_DATA),
            });
        });

        const modal = await openRdsModal(page);

        const bodyRows = modal.locator('.table tbody tr');
        const pendingRow = bodyRows.nth(0);

        // Clock icon should be visible and have cursor:pointer
        const clockIcon = pendingRow.locator('.bi-clock-history');
        await expect(clockIcon).toBeVisible();

        const cursorStyle = await clockIcon.evaluate((el) =>
            window.getComputedStyle(el).cursor
        );
        expect(cursorStyle).toBe('pointer');

        // Click the clock icon — confirmation modal must appear
        await clockIcon.click();
        const confirmModal = page.locator('#modal-resetDocumentState');
        await expect(confirmModal).toBeVisible({ timeout: 5000 });
    });

    /**
     * E-RDS-03: ERROR rows have X icon, grey background, not clickable
     * AC-RDS-04: il sistema NON DEVE permettere alcuna azione sulle righe ERROR
     */
    test('E-RDS-03: ERROR row has X icon with grey background and clicking does nothing', async ({ page }) => {
        await page.route('**/ajax_resetDocumentState_view.php**', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(MOCK_RDS_DATA),
            });
        });

        const modal = await openRdsModal(page);

        const bodyRows = modal.locator('.table tbody tr');
        const errorRow = bodyRows.nth(1);

        // Verify grey background
        const errorClasses = await errorRow.getAttribute('class');
        expect(errorClasses).toContain('bg-light');

        // X icon should be visible
        const xIcon = errorRow.locator('.bi-x-circle-fill');
        await expect(xIcon).toBeVisible();

        // Clicking the X icon must NOT open any modal
        await xIcon.click();
        const confirmModal = page.locator('#modal-resetDocumentState');
        await page.waitForTimeout(500);
        await expect(confirmModal).not.toBeVisible();
    });

    /**
     * E-RDS-04: Confirming change updates row status (table reloads, row becomes grey)
     * AC-RDS-03: QUANDO l'utente conferma, la tabella viene ricaricata, la riga diventa grigia
     */
    test('E-RDS-04: confirming reset changes row to ERROR state and table reloads', async ({ page }) => {
        let callCount = 0;

        // First call returns PENDING + ERROR; second call (after save) returns all ERROR
        await page.route('**/ajax_resetDocumentState_view.php**', async (route) => {
            callCount++;
            const data = callCount === 1
                ? MOCK_RDS_DATA
                : [
                    { id: 1, 'Draft ID': 'DRAFT_PENDING_1', 'Doc. PENDING': 0, 'Doc. ERROR': 2 },
                    { id: 2, 'Draft ID': 'DRAFT_ERROR_1', 'Doc. PENDING': 0, 'Doc. ERROR': 3 },
                ];
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(data),
            });
        });

        await page.route('**/ajax_resetDocumentState_save.php', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ success: true }),
            });
        });

        const modal = await openRdsModal(page);

        // Click clock icon on PENDING row
        const bodyRows = modal.locator('.table tbody tr');
        const clockIcon = bodyRows.nth(0).locator('.bi-clock-history');
        await clockIcon.click();

        const confirmModal = page.locator('#modal-resetDocumentState');
        await expect(confirmModal).toBeVisible({ timeout: 5000 });

        // Confirm the reset
        const confirmBtn = confirmModal.locator('.btn-warning');
        await confirmBtn.click();

        // Modal should close and table should reload
        await expect(confirmModal).not.toBeVisible({ timeout: 5000 });
        await page.waitForTimeout(500);

        // The formerly PENDING row should now be grey (bg-light)
        const updatedRows = modal.locator('.table tbody tr');
        const firstRowClass = await updatedRows.nth(0).getAttribute('class');
        expect(firstRowClass).toContain('bg-light');

        // And it should now have an X icon instead of a clock
        const xIcon = updatedRows.nth(0).locator('.bi-x-circle-fill');
        await expect(xIcon).toBeVisible();
    });

});
