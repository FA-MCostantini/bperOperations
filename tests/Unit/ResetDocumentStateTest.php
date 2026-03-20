<?php declare(strict_types=1);

require_once __DIR__ . '/../BperTestCase.php';

use FirstAdvisory\FAWill\model\Operations\AjaxRequest;
use FirstAdvisory\FAWill\model\Operations\ResetDocumentState;

/**
 * Unit tests for ResetDocumentState business logic.
 *
 * Test IDs: U-RDS-01, U-RDS-02
 */
class ResetDocumentStateTest extends BperTestCase
{
    // -------------------------------------------------------------------------
    // U-RDS-01: getName() returns 'resetDocumentState'
    // -------------------------------------------------------------------------

    /**
     * U-RDS-01: getName() returns the canonical operation identifier
     */
    public function testURDS01_GetNameReturnsResetDocumentState(): void
    {
        $operation = new ResetDocumentState();
        $this->assertSame('resetDocumentState', $operation->getName());
    }

    // -------------------------------------------------------------------------
    // U-RDS-02: updateStatus() with invalid id throws InvalidArgumentException
    // -------------------------------------------------------------------------

    /**
     * U-RDS-02: updateStatus() with id=0 throws InvalidArgumentException
     */
    public function testURDS02_UpdateStatusWithZeroIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ID draft non valido');

        $operation = new ResetDocumentState();
        $request   = new AjaxRequest('POST', 'update', ['id' => '0']);
        $operation->updateStatus($request);
    }

    /**
     * U-RDS-02: updateStatus() with negative id throws InvalidArgumentException
     */
    public function testURDS02_UpdateStatusWithNegativeIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ID draft non valido');

        $operation = new ResetDocumentState();
        $request   = new AjaxRequest('POST', 'update', ['id' => '-1']);
        $operation->updateStatus($request);
    }

    /**
     * U-RDS-02: updateStatus() with missing id parameter throws InvalidArgumentException
     */
    public function testURDS02_UpdateStatusWithMissingIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ID draft non valido');

        $operation = new ResetDocumentState();
        $request   = new AjaxRequest('POST', 'update', []);
        $operation->updateStatus($request);
    }
}
