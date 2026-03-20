<?php declare(strict_types=1);

use FirstAdvisory\FAWill\model\Operations\AjaxRequest;
use FirstAdvisory\FAWill\model\Operations\ForceAnnulment;
use FirstAdvisory\FAWill\model\Operations\ResetDocumentState;

/**
 * S-05, S-06 — Parameter Tampering
 *
 * Verifies that the backend rejects tampered id parameters (negative integers,
 * zero, non-numeric strings, null, and excessively large values) with an
 * InvalidArgumentException before any database interaction takes place.
 */
class ParameterTamperingTest extends BperTestCase
{
    private ForceAnnulment $forceAnnulment;
    private ResetDocumentState $resetDocumentState;

    protected function setUp(): void
    {
        $this->forceAnnulment     = new ForceAnnulment();
        $this->resetDocumentState = new ResetDocumentState();
    }

    // -------------------------------------------------------------------------
    // S-05: ForceAnnulment::delete() parameter tampering
    // -------------------------------------------------------------------------

    /**
     * S-05: Negative id must be rejected before touching the database.
     */
    public function testS05NegativeIdRejectedByForceAnnulment(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = new AjaxRequest('POST', '', ['id' => '-1']);
        $this->forceAnnulment->delete($request);
    }

    /**
     * S-05: Zero id (default when key is missing) must be rejected.
     */
    public function testS05ZeroIdRejectedByForceAnnulment(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = new AjaxRequest('POST', '', ['id' => '0']);
        $this->forceAnnulment->delete($request);
    }

    /**
     * S-05: Missing id key must be rejected (defaults to 0).
     */
    public function testS05MissingIdRejectedByForceAnnulment(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = new AjaxRequest('POST', '', []);
        $this->forceAnnulment->delete($request);
    }

    /**
     * S-06: Non-numeric string id is cast to (int) = 0 and must be rejected.
     */
    public function testS06StringIdCastToZeroRejectedByForceAnnulment(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = new AjaxRequest('POST', '', ['id' => 'abc']);
        $this->forceAnnulment->delete($request);
    }

    /**
     * S-06: Alphanumeric string id must be rejected.
     */
    public function testS06AlphanumericIdRejectedByForceAnnulment(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = new AjaxRequest('POST', '', ['id' => '1abc']);
        $this->forceAnnulment->delete($request);
    }

    /**
     * S-06: SQL injection string used as id must be cast to 0 and rejected.
     */
    public function testS06InjectionStringIdRejectedByForceAnnulment(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = new AjaxRequest('POST', '', ['id' => "1 OR 1=1"]);
        $this->forceAnnulment->delete($request);
    }

    // -------------------------------------------------------------------------
    // S-05 / S-06: ResetDocumentState::updateStatus() parameter tampering
    // -------------------------------------------------------------------------

    /**
     * S-05: Negative id must be rejected by ResetDocumentState::updateStatus().
     */
    public function testS05NegativeIdRejectedByResetDocumentState(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = new AjaxRequest('POST', '', ['id' => '-5']);
        $this->resetDocumentState->updateStatus($request);
    }

    /**
     * S-06: String id cast to 0 must be rejected by ResetDocumentState::updateStatus().
     */
    public function testS06StringIdRejectedByResetDocumentState(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = new AjaxRequest('POST', '', ['id' => 'abc']);
        $this->resetDocumentState->updateStatus($request);
    }

    /**
     * S-06: Zero id must be rejected by ResetDocumentState::updateStatus().
     */
    public function testS06ZeroIdRejectedByResetDocumentState(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = new AjaxRequest('POST', '', ['id' => '0']);
        $this->resetDocumentState->updateStatus($request);
    }
}
