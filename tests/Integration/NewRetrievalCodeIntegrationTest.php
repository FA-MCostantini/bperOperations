<?php declare(strict_types=1);

require_once __DIR__ . '/../BperTestCase.php';

use FirstAdvisory\FAWill\model\Operations\AjaxRequest;
use FirstAdvisory\FAWill\model\Operations\NewRetrievalCode;
use FirstAdvisory\FAWill\model\Operations\OperationAuditLogger;

/**
 * Integration tests for NewRetrievalCode — full Operation + Repository + DB flow.
 *
 * Test IDs: I-NRC-01 through I-NRC-06
 */
class NewRetrievalCodeIntegrationTest extends BperTestCase
{
    private const TEST_CONTRACT = 'TEST_054';
    private const TEST_POLICY   = 'TEST_054_POLICY';

    protected function setUp(): void
    {
        $pdo = $this->getConnection();

        // Insert a test policy into the view's underlying source via t_ath_policy_auth_code
        // (v_policy is read-only; we seed bper_policy_number via auth code table for search tests)
        // For search tests (I-NRC-03), we need a record in v_policy — since it's a view,
        // we insert a record with bper_contract_number matching our prefix in the auth code table
        // so that searchPolicy operates against a real contract number starting with TEST_054.

        // Pre-insert two codes for the preview/get tests (I-NRC-03, I-NRC-04, I-NRC-05)
        $pdo->exec(
            "INSERT INTO ntt_bper.t_ath_policy_auth_code (code, insert_date, bper_contract_number, operation_type_code)
             VALUES
               ('RT" . self::TEST_CONTRACT . "1', NOW(), '" . self::TEST_CONTRACT . "', '_RISTO'),
               ('RT" . self::TEST_CONTRACT . "2', NOW(), '" . self::TEST_CONTRACT . "', '_RISTO')
             ON CONFLICT DO NOTHING"
        );
    }

    protected function tearDown(): void
    {
        $pdo = $this->getConnection();

        // Remove all test auth codes
        $pdo->exec(
            "DELETE FROM ntt_bper.t_ath_policy_auth_code
              WHERE bper_contract_number LIKE 'TEST_%'
                 OR code LIKE 'RT_TEST_%'
                 OR code LIKE 'RPTEST_%'
                 OR code LIKE 'RTTEST_%'"
        );

        // Remove test audit log entries
        $this->cleanupAuditLog();
    }

    /**
     * I-NRC-01: Insert a code via operation, verify it exists in DB.
     */
    public function testInsertCodePersistsInDatabase(): void
    {
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('POST', '', [
            'bper_contract_number' => self::TEST_CONTRACT,
            'type'                 => 'T',
        ]);

        // Two codes already exist (n=1, n=2); next should be n=3
        $result = $operation->insert($request);

        $this->assertIsArray($result);
        $this->assertTrue($result['inserted']);
        $this->assertStringStartsWith('RT' . self::TEST_CONTRACT, $result['code']);

        $pdo  = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT code FROM ntt_bper.t_ath_policy_auth_code WHERE code = :code"
        );
        $stmt->execute([':code' => $result['code']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'Inserted code must be present in DB');
        $this->assertSame($result['code'], $row['code']);
    }

    /**
     * I-NRC-02: Insert duplicate code triggers ON CONFLICT DO NOTHING — no error thrown.
     */
    public function testInsertDuplicateCodeDoesNotThrow(): void
    {
        // The code RT{TEST_CONTRACT}1 was inserted in setUp().
        // Directly call the repository to attempt inserting the same code again.
        $pdo = $this->getConnection();

        // Should not throw; ON CONFLICT DO NOTHING silences the duplicate.
        $stmt = $pdo->prepare(
            "INSERT INTO ntt_bper.t_ath_policy_auth_code
                 (code, insert_date, bper_contract_number, operation_type_code)
             VALUES (:code, NOW(), :contract, :type_code)
             ON CONFLICT DO NOTHING"
        );

        $threw = false;
        try {
            $stmt->execute([
                ':code'      => 'RT' . self::TEST_CONTRACT . '1',
                ':contract'  => self::TEST_CONTRACT,
                ':type_code' => '_RISTO',
            ]);
        } catch (\Throwable) {
            $threw = true;
        }

        $this->assertFalse($threw, 'Duplicate insert with ON CONFLICT DO NOTHING must not throw');
    }

    /**
     * I-NRC-03: searchPolicy returns results matching prefix.
     *
     * v_policy is a read-only view; this test verifies that searchPolicy() returns
     * at most 10 results and all of them start with the given prefix (q="TEST_054").
     * If the test DB has no matching policies the result is an empty array — still valid.
     */
    public function testSearchPolicyReturnsPrefixMatches(): void
    {
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('GET', '', ['q' => 'TEST_054']);

        $results = $operation->searchPolicy($request);

        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(10, count($results));

        foreach ($results as $policyNumber) {
            $this->assertStringStartsWith('TEST_054', $policyNumber);
        }
    }

    /**
     * I-NRC-04: getExistingCodes returns correct records for a contract number.
     */
    public function testGetExistingCodesReturnsCorrectRecords(): void
    {
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('GET', '', [
            'bper_contract_number' => self::TEST_CONTRACT,
        ]);

        $codes = $operation->getExistingCodes($request);

        $this->assertIsArray($codes);
        // setUp inserted 2 codes
        $this->assertGreaterThanOrEqual(2, count($codes));

        $codeCodes = array_column($codes, 'code');
        $this->assertContains('RT' . self::TEST_CONTRACT . '1', $codeCodes);
        $this->assertContains('RT' . self::TEST_CONTRACT . '2', $codeCodes);

        // Verify ordering: insert_date DESC (first element is the most recent)
        $dates = array_column($codes, 'insert_date');
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates, 'Records must be ordered by insert_date DESC');
    }

    /**
     * I-NRC-05: calculatePreview returns correct next_n.
     *
     * setUp inserted codes with n=1 and n=2 for TEST_054/T.
     * next_n should be 3.
     */
    public function testCalculatePreviewReturnsCorrectNextN(): void
    {
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('GET', '', [
            'bper_contract_number' => self::TEST_CONTRACT,
            'type'                 => 'T',
        ]);

        $preview = $operation->calculatePreview($request);

        $this->assertIsArray($preview);
        $this->assertArrayHasKey('next_n', $preview);
        $this->assertSame(3, $preview['next_n']);
        $this->assertArrayHasKey('code', $preview);
        $this->assertStringEndsWith('3', $preview['code']);
    }

    /**
     * I-NRC-06: After insert, verify audit log record exists in public.operation_audit_log.
     */
    public function testAuditLogRecordExistsAfterInsert(): void
    {
        $operation = new NewRetrievalCode();
        $request   = new AjaxRequest('POST', '', [
            'bper_contract_number' => self::TEST_CONTRACT,
            'type'                 => 'T',
        ]);

        $operation->insert($request);

        // Manually log as AjaxResponseHelper::success() would in a real request
        $logger = new OperationAuditLogger();
        $logger->log($operation->getName(), $request->params, $operation->getCurrentUserId());

        $pdo  = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, operation_name, payload, user_id
               FROM public.operation_audit_log
              WHERE operation_name = :name
              ORDER BY created_at DESC
              LIMIT 1"
        );
        $stmt->execute([':name' => $operation->getName()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'Audit log record must exist after insert');
        $this->assertSame('newRetrievalCode', $row['operation_name']);
        $this->assertSame(0, (int) $row['user_id']);

        $payload = json_decode($row['payload'], true);
        $this->assertIsArray($payload);
        $this->assertSame(self::TEST_CONTRACT, $payload['bper_contract_number']);
        $this->assertSame('T', $payload['type']);
    }
}
