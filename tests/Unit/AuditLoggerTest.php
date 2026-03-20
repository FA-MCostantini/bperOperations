<?php declare(strict_types=1);

require_once __DIR__ . '/../BperTestCase.php';

use FirstAdvisory\FAWill\model\Operations\NewRetrievalCode;
use FirstAdvisory\FAWill\model\Operations\OperationAuditLogger;

/**
 * Unit tests for OperationAuditLogger.
 *
 * Test IDs: U-LOG-01, U-LOG-02, U-LOG-03
 */
class AuditLoggerTest extends BperTestCase
{
    private const TEST_OPERATION_NAME = 'TEST_unitAuditLog';

    protected function tearDown(): void
    {
        $this->cleanupAuditLog();
    }

    // -------------------------------------------------------------------------
    // U-LOG-01: log() inserts a record into public.operation_audit_log
    // -------------------------------------------------------------------------

    /**
     * U-LOG-01: log() writes a row to public.operation_audit_log
     */
    public function testULOG01_LogInsertsRecordIntoAuditTable(): void
    {
        $logger  = new OperationAuditLogger();
        $payload = ['bper_contract_number' => 'TEST_054', 'type' => 'T'];

        $logger->log(self::TEST_OPERATION_NAME, $payload, 0);

        $pdo  = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT id
               FROM public.operation_audit_log
              WHERE operation_name = :name
              ORDER BY created_at DESC
              LIMIT 1"
        );
        $stmt->execute([':name' => self::TEST_OPERATION_NAME]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'A record must exist in operation_audit_log after log()');
        $this->assertNotEmpty($row['id']);
    }

    // -------------------------------------------------------------------------
    // U-LOG-02: getCurrentUserId() on any AbstractOperation returns 0
    // -------------------------------------------------------------------------

    /**
     * U-LOG-02: AbstractOperation::getCurrentUserId() always returns 0 (no auth context)
     */
    public function testULOG02_GetCurrentUserIdReturnsZero(): void
    {
        // NewRetrievalCode extends AbstractOperation which provides getCurrentUserId()
        $operation = new NewRetrievalCode();
        $this->assertSame(0, $operation->getCurrentUserId());
    }

    // -------------------------------------------------------------------------
    // U-LOG-03: Logged record contains correct operation_name and payload
    // -------------------------------------------------------------------------

    /**
     * U-LOG-03: log() stores the exact operation_name and serialised payload
     */
    public function testULOG03_LogRecordContainsCorrectOperationNameAndPayload(): void
    {
        $logger  = new OperationAuditLogger();
        $payload = ['bper_contract_number' => 'TEST_054', 'type' => 'P', 'extra' => 'value'];

        $logger->log(self::TEST_OPERATION_NAME, $payload, 0);

        $pdo  = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT operation_name, payload, user_id
               FROM public.operation_audit_log
              WHERE operation_name = :name
              ORDER BY created_at DESC
              LIMIT 1"
        );
        $stmt->execute([':name' => self::TEST_OPERATION_NAME]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'Log record must be found');
        $this->assertSame(self::TEST_OPERATION_NAME, $row['operation_name']);
        $this->assertSame(0, (int) $row['user_id']);

        $decoded = json_decode($row['payload'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('TEST_054', $decoded['bper_contract_number']);
        $this->assertSame('P', $decoded['type']);
        $this->assertSame('value', $decoded['extra']);
    }
}
