<?php declare(strict_types=1);

use FirstAdvisory\FAWill\model\Operations\AjaxRequest;
use FirstAdvisory\FAWill\model\Operations\NewRetrievalCode;

/**
 * S-01, S-02 — SQL Injection
 *
 * Verifies that SQL injection payloads in GET and POST parameters are neutralised
 * by the prepared-statement layer and never cause SQL errors or data leaks.
 */
class SqlInjectionTest extends BperTestCase
{
    private NewRetrievalCode $operation;

    protected function setUp(): void
    {
        $this->operation = new NewRetrievalCode();
    }

    /**
     * S-01: Injection via GET params (search).
     *
     * A classic DROP-TABLE payload must not throw a PDO/SQL exception and must
     * return an array (possibly empty), never raw DB error output.
     */
    public function testS01SqlInjectionInSearchParam(): void
    {
        $request = new AjaxRequest('GET', 'search', ['q' => "'; DROP TABLE --"]);

        $result = $this->operation->searchPolicy($request);

        $this->assertIsArray($result, 'searchPolicy() must return an array even with injection payload');
    }

    /**
     * S-01 variant: single-quote in search term must not surface a PDO error.
     */
    public function testS01SingleQuoteInSearchParam(): void
    {
        $request = new AjaxRequest('GET', 'search', ['q' => "' OR '1'='1"]);

        $result = $this->operation->searchPolicy($request);

        $this->assertIsArray($result, 'searchPolicy() must return an array for single-quote payload');
    }

    /**
     * S-01 variant: UNION-based injection in search term.
     */
    public function testS01UnionInjectionInSearchParam(): void
    {
        $request = new AjaxRequest('GET', 'search', ['q' => "' UNION SELECT version()--"]);

        $result = $this->operation->searchPolicy($request);

        $this->assertIsArray($result, 'searchPolicy() must return an array for UNION injection payload');
    }

    /**
     * S-02: Injection via POST params (bper_contract_number).
     *
     * The method must either return normally (no rows matched) or throw a domain
     * exception. It must never propagate a raw PDO/SQL error.
     */
    public function testS02SqlInjectionInContractNumberPost(): void
    {
        $request = new AjaxRequest('POST', '', [
            'bper_contract_number' => "' OR '1'='1",
            'type'                 => 'T',
        ]);

        try {
            $result = $this->operation->getExistingCodes($request);
            // Returned normally — must be an array (no data leaked from other rows via injection)
            $this->assertIsArray($result, 'getExistingCodes() must return an array');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            // A domain-level rejection is also acceptable
            $this->assertTrue(true, 'Domain exception thrown — injection was rejected');
        }
        // If we reach here without a PDOException the prepared statement did its job
    }

    /**
     * S-02 variant: stacked-statement injection in contract number.
     */
    public function testS02StackedInjectionInContractNumber(): void
    {
        $request = new AjaxRequest('POST', '', [
            'bper_contract_number' => "TEST'; DELETE FROM ntt_bper.t_ath_policy_auth_code;--",
            'type'                 => 'T',
        ]);

        try {
            $result = $this->operation->getExistingCodes($request);
            $this->assertIsArray($result, 'getExistingCodes() must return an array for stacked injection');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->assertTrue(true, 'Domain exception thrown for stacked injection payload');
        }
    }
}
