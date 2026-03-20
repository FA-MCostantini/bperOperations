<?php declare(strict_types=1);

require_once __DIR__ . '/../BperTestCase.php';

use FirstAdvisory\FAWill\model\Operations\AjaxRequest;
use FirstAdvisory\FAWill\model\Operations\ForceAnnulment;

/**
 * Unit tests for ForceAnnulment business logic.
 *
 * Test IDs: U-FA-01, U-FA-02
 */
class ForceAnnulmentTest extends BperTestCase
{
    // -------------------------------------------------------------------------
    // U-FA-01: getName() returns 'forceAnnulment'
    // -------------------------------------------------------------------------

    /**
     * U-FA-01: getName() returns the canonical operation identifier
     */
    public function testUFA01_GetNameReturnsForceAnnulment(): void
    {
        $operation = new ForceAnnulment();
        $this->assertSame('forceAnnulment', $operation->getName());
    }

    // -------------------------------------------------------------------------
    // U-FA-02: delete() with invalid id throws InvalidArgumentException
    // -------------------------------------------------------------------------

    /**
     * U-FA-02: delete() with id=0 throws InvalidArgumentException
     */
    public function testUFA02_DeleteWithZeroIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ID operazione non valido');

        $operation = new ForceAnnulment();
        $request   = new AjaxRequest('POST', 'delete', ['id' => '0']);
        $operation->delete($request);
    }

    /**
     * U-FA-02: delete() with negative id throws InvalidArgumentException
     */
    public function testUFA02_DeleteWithNegativeIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ID operazione non valido');

        $operation = new ForceAnnulment();
        $request   = new AjaxRequest('POST', 'delete', ['id' => '-5']);
        $operation->delete($request);
    }

    /**
     * U-FA-02: delete() with missing id parameter throws InvalidArgumentException
     */
    public function testUFA02_DeleteWithMissingIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ID operazione non valido');

        $operation = new ForceAnnulment();
        $request   = new AjaxRequest('POST', 'delete', []);
        $operation->delete($request);
    }
}
