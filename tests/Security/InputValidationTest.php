<?php declare(strict_types=1);

use FirstAdvisory\FAWill\model\Operations\AjaxRequest;
use FirstAdvisory\FAWill\model\Operations\NewRetrievalCode;

/**
 * S-08, S-09 — Input Validation
 *
 * Verifies that the backend rejects inputs that violate domain constraints:
 * - S-08: type values other than 'P' or 'T'
 * - S-09: empty or whitespace-only contract numbers
 */
class InputValidationTest extends BperTestCase
{
    private NewRetrievalCode $operation;

    protected function setUp(): void
    {
        $this->operation = new NewRetrievalCode();
    }

    // -------------------------------------------------------------------------
    // S-08: Invalid redemption type
    // -------------------------------------------------------------------------

    /**
     * S-08: Type 'X' must be rejected by the match expression in
     * NewRetrievalCode::getOperationTypeCode().
     */
    public function testS08InvalidTypeXThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Tipo non valido/i');

        $request = new AjaxRequest('GET', 'calc', [
            'bper_contract_number' => 'TEST_SEC',
            'type'                 => 'X',
        ]);
        // calculatePreview calls generateCode -> repository is hit first, but
        // insert() goes through getOperationTypeCode() which contains the match.
        // Use insert() to exercise the validation path directly.
        $this->operation->insert($request);
    }

    /**
     * S-08: Empty type string must be rejected.
     */
    public function testS08EmptyTypeThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = new AjaxRequest('GET', 'calc', [
            'bper_contract_number' => 'TEST_SEC',
            'type'                 => '',
        ]);
        $this->operation->insert($request);
    }

    /**
     * S-08: Lowercase 'p' must be rejected (type is case-sensitive).
     */
    public function testS08LowercaseTypeThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = new AjaxRequest('GET', 'calc', [
            'bper_contract_number' => 'TEST_SEC',
            'type'                 => 'p',
        ]);
        $this->operation->insert($request);
    }

    /**
     * S-08: Numeric type value must be rejected.
     */
    public function testS08NumericTypeThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = new AjaxRequest('GET', 'calc', [
            'bper_contract_number' => 'TEST_SEC',
            'type'                 => '1',
        ]);
        $this->operation->insert($request);
    }

    // -------------------------------------------------------------------------
    // S-09: Empty or invalid contract number
    // -------------------------------------------------------------------------

    /**
     * S-09: Empty contract number on the 'tabella' action must return an empty
     * array (no rows can match an empty string in the prepared statement).
     */
    public function testS09EmptyContractNumberReturnsEmptyArray(): void
    {
        $request = new AjaxRequest('GET', 'tabella', ['bper_contract_number' => '']);

        $result = $this->operation->getExistingCodes($request);

        $this->assertIsArray($result, 'getExistingCodes() must return an array');
        $this->assertEmpty($result, 'Empty contract number must return empty result set');
    }

    /**
     * S-09: Whitespace-only contract number must return an empty array.
     */
    public function testS09WhitespaceContractNumberReturnsEmptyArray(): void
    {
        $request = new AjaxRequest('GET', 'tabella', ['bper_contract_number' => '   ']);

        $result = $this->operation->getExistingCodes($request);

        $this->assertIsArray($result, 'getExistingCodes() must return an array');
        $this->assertEmpty($result, 'Whitespace-only contract number must return empty result set');
    }

    /**
     * S-09: Contract number with special characters must not cause a SQL error.
     */
    public function testS09SpecialCharactersInContractNumberAreSafe(): void
    {
        $request = new AjaxRequest('GET', 'tabella', ['bper_contract_number' => "!@#$%^&*()_+"]);

        $result = $this->operation->getExistingCodes($request);

        $this->assertIsArray($result, 'getExistingCodes() must return an array for special characters');
    }
}
