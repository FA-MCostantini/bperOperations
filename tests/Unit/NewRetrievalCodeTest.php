<?php declare(strict_types=1);

require_once __DIR__ . '/../BperTestCase.php';

use FirstAdvisory\FAWill\model\Operations\AjaxRequest;
use FirstAdvisory\FAWill\model\Operations\NewRetrievalCode;

/**
 * Unit tests for NewRetrievalCode business logic.
 *
 * Test IDs: U-NRC-01 through U-NRC-08
 *
 * Tests that require DB access (U-NRC-03 through U-NRC-06) use real fixture data
 * inserted in setUp() and cleaned up in tearDown().
 * Private methods generateCode() and getOperationTypeCode() are exercised through
 * the public API (calculatePreview() / insert()).
 */
class NewRetrievalCodeTest extends BperTestCase
{
    private const CONTRACT_T  = 'TEST_05421607';
    private const CONTRACT_P  = 'TEST_05435891';
    private const CONTRACT_GAP = 'TEST_GAPTEST1';
    private const CONTRACT_FULL = 'TEST_FULLCODE';

    protected function setUp(): void
    {
        $pdo = $this->getConnection();

        // U-NRC-03: codes n=1,2,3 for CONTRACT_T / tipo T
        $pdo->exec(
            "INSERT INTO ntt_bper.t_ath_policy_auth_code
                 (code, insert_date, bper_contract_number, operation_type_code)
             VALUES
               ('RT" . self::CONTRACT_T . "1', NOW(), '" . self::CONTRACT_T . "', '_RISTO'),
               ('RT" . self::CONTRACT_T . "2', NOW(), '" . self::CONTRACT_T . "', '_RISTO'),
               ('RT" . self::CONTRACT_T . "3', NOW(), '" . self::CONTRACT_T . "', '_RISTO')
             ON CONFLICT DO NOTHING"
        );

        // U-NRC-04: codes n=1,3 (gap) for CONTRACT_GAP / tipo T
        $pdo->exec(
            "INSERT INTO ntt_bper.t_ath_policy_auth_code
                 (code, insert_date, bper_contract_number, operation_type_code)
             VALUES
               ('RT" . self::CONTRACT_GAP . "1', NOW(), '" . self::CONTRACT_GAP . "', '_RISTO'),
               ('RT" . self::CONTRACT_GAP . "3', NOW(), '" . self::CONTRACT_GAP . "', '_RISTO')
             ON CONFLICT DO NOTHING"
        );

        // U-NRC-05: 9 codes for CONTRACT_FULL / tipo T
        $values = [];
        for ($i = 1; $i <= 9; $i++) {
            $values[] = "('RT" . self::CONTRACT_FULL . "{$i}', NOW(), '" . self::CONTRACT_FULL . "', '_RISTO')";
        }
        $pdo->exec(
            "INSERT INTO ntt_bper.t_ath_policy_auth_code
                 (code, insert_date, bper_contract_number, operation_type_code)
             VALUES " . implode(', ', $values) . "
             ON CONFLICT DO NOTHING"
        );
    }

    protected function tearDown(): void
    {
        $pdo = $this->getConnection();
        $pdo->exec(
            "DELETE FROM ntt_bper.t_ath_policy_auth_code
              WHERE bper_contract_number LIKE 'TEST_%'"
        );
    }

    // -------------------------------------------------------------------------
    // U-NRC-01: Code generation tipo T → prefix RT + contractNumber + progressive
    // -------------------------------------------------------------------------

    /**
     * U-NRC-01: tipo=T with no existing codes produces code "RT054216071" (n=1)
     */
    public function testUNRC01_GeneratesCodeForTipoT(): void
    {
        $contractNumber = 'TEST_054216NX';   // unique — no fixture rows for this contract
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('GET', 'calc', [
            'bper_contract_number' => $contractNumber,
            'type'                 => 'T',
        ]);

        $result = $operation->calculatePreview($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertStringStartsWith('RT' . $contractNumber, $result['code']);
        $this->assertSame(1, $result['next_n']);
        $this->assertSame('RT' . $contractNumber . '1', $result['code']);
    }

    // -------------------------------------------------------------------------
    // U-NRC-02: Code generation tipo P → prefix RP
    // -------------------------------------------------------------------------

    /**
     * U-NRC-02: tipo=P with no existing codes produces code with prefix RP (n=1)
     */
    public function testUNRC02_GeneratesCodeForTipoP(): void
    {
        $contractNumber = 'TEST_054358NX';   // unique — no fixture rows
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('GET', 'calc', [
            'bper_contract_number' => $contractNumber,
            'type'                 => 'P',
        ]);

        $result = $operation->calculatePreview($request);

        $this->assertIsArray($result);
        $this->assertStringStartsWith('RP' . $contractNumber, $result['code']);
        $this->assertSame(1, $result['next_n']);
        $this->assertSame('RP' . $contractNumber . '1', $result['code']);
    }

    // -------------------------------------------------------------------------
    // U-NRC-03: Operation type mapping P→_RISPA, T→_RISTO (via insert)
    // -------------------------------------------------------------------------

    /**
     * U-NRC-03: insert() with tipo=T stores operation_type_code=_RISTO in DB
     */
    public function testUNRC03_OperationTypeMappingT_IsRISTO(): void
    {
        $contractNumber = 'TEST_TYPEMAP_T1';
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('POST', 'insert', [
            'bper_contract_number' => $contractNumber,
            'type'                 => 'T',
        ]);

        $result = $operation->insert($request);

        $this->assertTrue($result['inserted']);

        $pdo  = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT operation_type_code
               FROM ntt_bper.t_ath_policy_auth_code
              WHERE code = :code"
        );
        $stmt->execute([':code' => $result['code']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertSame('_RISTO', $row['operation_type_code']);
    }

    /**
     * U-NRC-03 (P variant): insert() with tipo=P stores operation_type_code=_RISPA in DB
     */
    public function testUNRC03_OperationTypeMappingP_IsRISPA(): void
    {
        $contractNumber = 'TEST_TYPEMAP_P1';
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('POST', 'insert', [
            'bper_contract_number' => $contractNumber,
            'type'                 => 'P',
        ]);

        $result = $operation->insert($request);

        $this->assertTrue($result['inserted']);

        $pdo  = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT operation_type_code
               FROM ntt_bper.t_ath_policy_auth_code
              WHERE code = :code"
        );
        $stmt->execute([':code' => $result['code']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertSame('_RISPA', $row['operation_type_code']);
    }

    // -------------------------------------------------------------------------
    // U-NRC-04: Progressive starts at 1 when no existing codes
    // -------------------------------------------------------------------------

    /**
     * U-NRC-04: calculatePreview returns next_n=1 when no codes exist for that contract
     */
    public function testUNRC04_ProgressiveStartsAtOneWhenNoCodes(): void
    {
        $contractNumber = 'TEST_FRESH_CONTR';
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('GET', 'calc', [
            'bper_contract_number' => $contractNumber,
            'type'                 => 'T',
        ]);

        $result = $operation->calculatePreview($request);

        $this->assertSame(1, $result['next_n']);
        $this->assertStringEndsWith('1', $result['code']);
    }

    // -------------------------------------------------------------------------
    // U-NRC-05: Progressive MAX+1 with existing codes
    // -------------------------------------------------------------------------

    /**
     * U-NRC-05: calculatePreview with codes n=1,2,3 returns next_n=4
     */
    public function testUNRC05_ProgressiveIsMaxPlusOneWithExistingCodes(): void
    {
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('GET', 'calc', [
            'bper_contract_number' => self::CONTRACT_T,
            'type'                 => 'T',
        ]);

        $result = $operation->calculatePreview($request);

        $this->assertSame(4, $result['next_n']);
        $this->assertStringEndsWith('4', $result['code']);
    }

    /**
     * U-NRC-05 (gap variant): With codes n=1,3 (gap), MAX+1 = 4 (gaps are NOT filled)
     */
    public function testUNRC05_ProgressiveIsMaxPlusOneIgnoringGaps(): void
    {
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('GET', 'calc', [
            'bper_contract_number' => self::CONTRACT_GAP,
            'type'                 => 'T',
        ]);

        $result = $operation->calculatePreview($request);

        // MAX is 3; next_n = 4
        $this->assertSame(4, $result['next_n']);
    }

    // -------------------------------------------------------------------------
    // U-NRC-06: Limit reached (n > 9) throws RuntimeException
    // -------------------------------------------------------------------------

    /**
     * U-NRC-06: calculatePreview throws RuntimeException when 9 codes already exist
     */
    public function testUNRC06_LimitReachedThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Limite massimo codici raggiunto');

        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('GET', 'calc', [
            'bper_contract_number' => self::CONTRACT_FULL,
            'type'                 => 'T',
        ]);

        $operation->calculatePreview($request);
    }

    // -------------------------------------------------------------------------
    // U-NRC-07: searchPolicy with < 2 chars returns empty array
    // -------------------------------------------------------------------------

    /**
     * U-NRC-07: searchPolicy with empty string returns []
     */
    public function testUNRC07_SearchPolicyWithEmptyStringReturnsEmptyArray(): void
    {
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('GET', 'search', ['q' => '']);

        $this->assertSame([], $operation->searchPolicy($request));
    }

    /**
     * U-NRC-07: searchPolicy with single character returns []
     */
    public function testUNRC07_SearchPolicyWithOneCharReturnsEmptyArray(): void
    {
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('GET', 'search', ['q' => 'X']);

        $this->assertSame([], $operation->searchPolicy($request));
    }

    // -------------------------------------------------------------------------
    // U-NRC-08: getName() returns 'newRetrievalCode'
    // -------------------------------------------------------------------------

    /**
     * U-NRC-08: getName() returns the canonical operation identifier
     */
    public function testUNRC08_GetNameReturnsNewRetrievalCode(): void
    {
        $operation = new NewRetrievalCode();
        $this->assertSame('newRetrievalCode', $operation->getName());
    }

    // -------------------------------------------------------------------------
    // U-NRC-09: getExistingCodes returns operation_type_code as DB values (_RISTO/_RISPA)
    // Ensures JS hasUnconsumedCodeForType() can match on the correct codes.
    // -------------------------------------------------------------------------

    /**
     * U-NRC-09: getExistingCodes returns _RISTO/_RISPA in operation_type_code field
     */
    public function testUNRC09_ExistingCodesReturnDbOperationTypeCodes(): void
    {
        // Insert one code for each type
        $pdo = $this->getConnection();
        $contractNumber = 'TEST_OPTYPE_CHK';
        $pdo->exec(
            "INSERT INTO ntt_bper.t_ath_policy_auth_code
                 (code, insert_date, bper_contract_number, operation_type_code)
             VALUES
               ('RT{$contractNumber}1', NOW(), '{$contractNumber}', '_RISTO'),
               ('RP{$contractNumber}1', NOW(), '{$contractNumber}', '_RISPA')
             ON CONFLICT DO NOTHING"
        );

        $operation = new NewRetrievalCode();
        $request = new AjaxRequest('GET', 'tabella', [
            'bper_contract_number' => $contractNumber,
        ]);

        $codes = $operation->getExistingCodes($request);

        $this->assertCount(2, $codes);

        $opTypes = array_column($codes, 'operation_type_code');
        $this->assertContains('_RISTO', $opTypes, 'Must contain _RISTO for tipo T');
        $this->assertContains('_RISPA', $opTypes, 'Must contain _RISPA for tipo P');

        // Verify these are NOT the short codes 'T'/'P'
        $this->assertNotContains('T', $opTypes);
        $this->assertNotContains('P', $opTypes);
    }
}
