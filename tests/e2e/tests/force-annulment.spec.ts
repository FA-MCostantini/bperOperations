import { test, expect } from '@playwright/test';

const TEST_PAGE = '/tests/e2e/test-page.php';

/**
 * E-FA — forceAnnulment E2E tests
 *
 * Covers scenarios E-FA-01 through E-FA-06 as defined in
 * docs/EXPLAIN_TEST.md section 3 and docs/ACCEPTANCE_CRITERIA.md AC-FA-*.
 */

/** Open the ForceAnnulment modal and wait for the table to load. */
async function openFaModal(page: import('@playwright/test').Page) {
    await page.goto(TEST_PAGE);
    await page.waitForSelector('.operation-card', { timeout: 10000 });

    const faCard = page.locator('.operation-card:not(.disabled)', { hasText: 'Annullamento' });
    const fallback = page.locator('.operation-card:not(.disabled)').nth(1);
    const card = (await faCard.count()) > 0 ? faCard.first() : fallback;
    await card.click();

    const modal = page.locator('#operationModal');
    await expect(modal).toBeVisible({ timeout: 10000 });

    // Wait for the table to be rendered inside the modal
    await page.waitForSelector('#operationModal .table', { timeout: 8000 });
    return modal;
}

test.describe('E-FA — forceAnnulment', () => {

    /**
     * E-FA-01: Table loads with data and pagination at 20 rows
     * AC-FA-01: la tabella si popola, la paginazione mostra 20 righe di default
     */
    test('E-FA-01: table loads with data on modal open', async ({ page }) => {
        const modal = await openFaModal(page);

        const table = modal.locator('.table');
        await expect(table).toBeVisible();

        // Page-size select should default to 20
        const pageSizeSelect = modal.locator('select.form-select');
        await expect(pageSizeSelect).toHaveValue('20');

        // At least the header row should exist
        const headerCells = table.locator('thead th');
        expect(await headerCells.count()).toBeGreaterThan(0);
    });

    /**
     * E-FA-02: Pagination works — change to 50 rows per page
     * AC-FA-02: QUANDO si seleziona 50, la tabella mostra 50 righe per pagina
     */
    test('E-FA-02: changing page size to 50 updates displayed rows', async ({ page }) => {
        // Seed enough data to have more than 20 rows
        await page.route('**/ajax_forceAnnulment_view.php**', async (route) => {
            const url = route.request().url();
            if (url.includes('action=tabella')) {
                const rows = Array.from({ length: 55 }, (_, i) => ({
                    id: i + 1,
                    'Numero polizza': `POL_${String(i + 1).padStart(4, '0')}`,
                    'Data invio': `2024-01-${String((i % 28) + 1).padStart(2, '0')}`,
                    'Stato': 'ACTIVE',
                }));
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ success: true, data: rows }),
                });
            } else {
                await route.continue();
            }
        });

        const modal = await openFaModal(page);

        // Switch page size to 50
        const pageSizeSelect = modal.locator('select.form-select');
        await pageSizeSelect.selectOption('50');
        await page.waitForTimeout(300);

        // The table body should now show 50 data rows (the first 50 out of 55)
        const bodyRows = modal.locator('.table tbody tr');
        const visibleCount = await bodyRows.count();
        expect(visibleCount).toBeLessThanOrEqual(50);
        expect(visibleCount).toBeGreaterThan(20);
    });

    /**
     * E-FA-03: Filter narrows results
     * AC-FA-03: QUANDO si digita nel campo filtro, solo le righe corrispondenti sono mostrate
     */
    test('E-FA-03: filter input narrows table rows', async ({ page }) => {
        // Mock a predictable dataset
        await page.route('**/ajax_forceAnnulment_view.php**', async (route) => {
            const url = route.request().url();
            if (url.includes('action=tabella')) {
                const rows = [
                    { id: 1, 'Numero polizza': 'POL_ALPHA', 'Data invio': '2024-01-01', 'Stato': 'ACTIVE' },
                    { id: 2, 'Numero polizza': 'POL_BETA', 'Data invio': '2024-01-02', 'Stato': 'ACTIVE' },
                    { id: 3, 'Numero polizza': 'POL_ALPHA_2', 'Data invio': '2024-01-03', 'Stato': 'ACTIVE' },
                ];
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ success: true, data: rows }),
                });
            } else {
                await route.continue();
            }
        });

        const modal = await openFaModal(page);

        // All 3 rows should be visible initially
        let bodyRows = modal.locator('.table tbody tr');
        await expect(bodyRows).toHaveCount(3);

        // Filter by "ALPHA" — should leave 2 rows
        const filterInput = modal.locator('input[placeholder="Cerca..."]');
        await filterInput.fill('ALPHA');
        await page.waitForTimeout(300);

        bodyRows = modal.locator('.table tbody tr');
        await expect(bodyRows).toHaveCount(2);
    });

    /**
     * E-FA-04: Sort by column header
     * AC-FA-04: QUANDO si clicca su un header di colonna, le righe si ordinano
     */
    test('E-FA-04: clicking column header sorts rows', async ({ page }) => {
        await page.route('**/ajax_forceAnnulment_view.php**', async (route) => {
            const url = route.request().url();
            if (url.includes('action=tabella')) {
                const rows = [
                    { id: 1, 'Data invio': '2024-03-15', 'Numero polizza': 'POL_C' },
                    { id: 2, 'Data invio': '2024-01-10', 'Numero polizza': 'POL_A' },
                    { id: 3, 'Data invio': '2024-02-20', 'Numero polizza': 'POL_B' },
                ];
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ success: true, data: rows }),
                });
            } else {
                await route.continue();
            }
        });

        const modal = await openFaModal(page);

        // Click the "Data invio" column header to sort ascending
        const dateHeader = modal.locator('thead th', { hasText: 'Data invio' });
        await dateHeader.click();
        await page.waitForTimeout(300);

        // The sort icon should now be visible on that column
        await expect(dateHeader.locator('i.bi-sort-up, i.bi-sort-down')).toBeVisible();

        // First row should now be the earliest date (2024-01-10)
        const firstRowCells = modal.locator('.table tbody tr').first().locator('td');
        const firstDateCell = await firstRowCells.allTextContents();
        const concatenated = firstDateCell.join(' ');
        expect(concatenated).toContain('2024-01-10');

        // Click again to toggle descending
        await dateHeader.click();
        await page.waitForTimeout(300);

        const firstRowCellsDesc = modal.locator('.table tbody tr').first().locator('td');
        const firstDateCellDesc = await firstRowCellsDesc.allTextContents();
        const concatenatedDesc = firstDateCellDesc.join(' ');
        expect(concatenatedDesc).toContain('2024-03-15');
    });

    /**
     * E-FA-05: Delete shows confirmation modal, confirmed delete removes row
     * AC-FA-05: QUANDO si clicca il cestino → conferma, la riga scompare
     */
    test('E-FA-05: delete icon shows confirmation modal and confirmed delete removes row', async ({ page }) => {
        let rowCount = 2;
        await page.route('**/ajax_forceAnnulment_view.php**', async (route) => {
            const url = route.request().url();
            if (url.includes('action=tabella')) {
                const rows = Array.from({ length: rowCount }, (_, i) => ({
                    id: i + 1,
                    'Numero polizza': `POL_${i + 1}`,
                    'Data invio': '2024-01-01',
                }));
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ success: true, data: rows }),
                });
            } else {
                await route.continue();
            }
        });

        await page.route('**/ajax_forceAnnulment_save.php', async (route) => {
            rowCount = 1; // simulate deletion
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ success: true }),
            });
        });

        const modal = await openFaModal(page);

        // Click the trash icon on the first row
        const trashIcon = modal.locator('.bi-trash').first();
        await expect(trashIcon).toBeVisible();
        await trashIcon.click();

        // Confirm modal should appear
        const deleteModal = page.locator('#deleteConfirmModal');
        await expect(deleteModal).toBeVisible({ timeout: 5000 });

        // Confirm deletion
        const confirmBtn = deleteModal.locator('.btn-danger');
        await confirmBtn.click();

        // Delete modal should close
        await expect(deleteModal).not.toBeVisible({ timeout: 5000 });

        // Table should reload with one fewer row
        await page.waitForTimeout(500);
        const bodyRows = modal.locator('.table tbody tr');
        await expect(bodyRows).toHaveCount(1);
    });

    /**
     * E-FA-06: Cancelled delete keeps row
     * E-FA-06 maps to the "annullo" scenario from the spec — cancelled delete keeps row
     */
    test('E-FA-06: cancelling delete keeps the row in the table', async ({ page }) => {
        await page.route('**/ajax_forceAnnulment_view.php**', async (route) => {
            const url = route.request().url();
            if (url.includes('action=tabella')) {
                const rows = [
                    { id: 1, 'Numero polizza': 'POL_KEEP', 'Data invio': '2024-01-01' },
                ];
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ success: true, data: rows }),
                });
            } else {
                await route.continue();
            }
        });

        const modal = await openFaModal(page);

        // Click trash icon
        const trashIcon = modal.locator('.bi-trash').first();
        await trashIcon.click();

        const deleteModal = page.locator('#deleteConfirmModal');
        await expect(deleteModal).toBeVisible({ timeout: 5000 });

        // Click Annulla (cancel button — data-bs-dismiss="modal")
        const cancelBtn = deleteModal.locator('.btn-secondary');
        await cancelBtn.click();

        await expect(deleteModal).not.toBeVisible({ timeout: 5000 });

        // The row must still be present
        const bodyRows = modal.locator('.table tbody tr');
        await expect(bodyRows).toHaveCount(1);
        await expect(bodyRows.first()).toContainText('POL_KEEP');
    });

});
