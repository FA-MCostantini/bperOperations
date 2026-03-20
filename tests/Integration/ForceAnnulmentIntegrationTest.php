<?php declare(strict_types=1);

require_once __DIR__ . '/../BperTestCase.php';

use FirstAdvisory\FAWill\model\Operations\AjaxRequest;
use FirstAdvisory\FAWill\model\Operations\ForceAnnulment;
use FirstAdvisory\FAWill\model\Operations\OperationAuditLogger;

/**
 * Integration tests for ForceAnnulment — full Operation + Repository + DB flow.
 *
 * Test IDs: I-FA-01 through I-FA-04
 */
class ForceAnnulmentIntegrationTest extends BperTestCase
{
    private const TEST_BPER_POLICY_NUMBER    = 'TEST_FA_POLICY';
    private const TEST_COMPANY_OPERATION_ID  = 'TEST_FA_OP_001';
    private const TEST_OPERATION_TYPE_DESC   = 'TEST_FA_OP_DESC';
    private const TEST_OPERATION_TYPE_CODE   = 'TSTFA';

    /** @var int|null Inserted t_param_operation_type.id */
    private ?int $operationTypeId = null;

    /** @var int|null Inserted t_policy_operation.id */
    private ?int $operationId = null;

    /** @var int|null Inserted t_policy_operation_draft.id */
    private ?int $draftId = null;

    protected function setUp(): void
    {
        $pdo = $this->getConnection();

        // Sync sequences to avoid duplicate key conflicts with existing data
        foreach (['t_param_operation_type', 't_policy_operation', 't_policy_operation_draft', 't_int_policy_operation_docs'] as $t) {
            $pdo->exec("SELECT setval(pg_get_serial_sequence('ntt_bper.{$t}', 'id'), COALESCE((SELECT MAX(id) FROM ntt_bper.{$t}), 0) + 1, false)");
        }

        // 1. Insert a test operation type
        $stmt = $pdo->prepare(
            "INSERT INTO ntt_bper.t_param_operation_type (operation_desc, operation_code)
             VALUES (:desc, :code)
             RETURNING id"
        );
        $stmt->execute([
            ':desc' => self::TEST_OPERATION_TYPE_DESC,
            ':code' => self::TEST_OPERATION_TYPE_CODE,
        ]);
        $this->operationTypeId = (int) $stmt->fetchColumn();

        // 2. Insert a test policy operation (status PENDING)
        $stmt = $pdo->prepare(
            "INSERT INTO ntt_bper.t_policy_operation
                 (t_param_operation_type_id, operation_status, bper_policy_number, company_operation_id,
                  company_code, company_policy_number, premium, sent_date,
                  user_abi, user_agency_code, user_cab, iban,
                  customer_ndg, fiscal_code, fiscal_code_lgrp, code_rapporto, product_code)
             VALUES
                 (:type_id, 'PENDING', :bper_policy, :company_op_id,
                  'TEST_CO', 'TEST_CPN', 100.00, NOW(),
                  'TSABI', 'TSAGC', 'TSCAB', 'TEST_IBAN',
                  'TEST_NDG', 'TEST_FC', 'TEST_FCLGRP', 'TEST_CR', 'TEST_PC')
             RETURNING id"
        );
        $stmt->execute([
            ':type_id'        => $this->operationTypeId,
            ':bper_policy'    => self::TEST_BPER_POLICY_NUMBER,
            ':company_op_id'  => self::TEST_COMPANY_OPERATION_ID,
        ]);
        $this->operationId = (int) $stmt->fetchColumn();

        // 3. Insert a test draft linked to the operation
        $stmt = $pdo->prepare(
            "INSERT INTO ntt_bper.t_policy_operation_draft (policy_operation_id, bper_policy_number)
             VALUES (:op_id, :bper_policy)
             RETURNING id"
        );
        $stmt->execute([
            ':op_id'      => $this->operationId,
            ':bper_policy' => self::TEST_BPER_POLICY_NUMBER,
        ]);
        $this->draftId = (int) $stmt->fetchColumn();

        // 4. Insert test docs linked to the draft (t_int_policy_operation_docs)
        $pdo->prepare(
            "INSERT INTO ntt_bper.t_int_policy_operation_docs (t_policy_operation_draft_id)
             VALUES (:draft_id), (:draft_id2)"
        )->execute([
            ':draft_id'  => $this->draftId,
            ':draft_id2' => $this->draftId,
        ]);
    }

    protected function tearDown(): void
    {
        $pdo = $this->getConnection();

        // Remove docs first (FK dependency)
        if ($this->draftId !== null) {
            $pdo->prepare(
                "DELETE FROM ntt_bper.t_int_policy_operation_docs WHERE t_policy_operation_draft_id = :id"
            )->execute([':id' => $this->draftId]);

            $pdo->prepare(
                "DELETE FROM ntt_bper.t_policy_operation_draft WHERE id = :id"
            )->execute([':id' => $this->draftId]);
        }

        if ($this->operationId !== null) {
            $pdo->prepare(
                "DELETE FROM ntt_bper.t_policy_operation WHERE id = :id"
            )->execute([':id' => $this->operationId]);
        }

        if ($this->operationTypeId !== null) {
            $pdo->prepare(
                "DELETE FROM ntt_bper.t_param_operation_type WHERE id = :id"
            )->execute([':id' => $this->operationTypeId]);
        }

        $this->cleanupAuditLog();

        $this->operationTypeId = null;
        $this->operationId     = null;
        $this->draftId         = null;
    }

    /**
     * I-FA-01: Delete via operation sets status=CANCELLED and removes draft/docs.
     */
    public function testDeleteSetsStatusCancelledAndRemovesDraftAndDocs(): void
    {
        $operation = new ForceAnnulment();
        $request   = new AjaxRequest('POST', '', ['id' => (string) $this->operationId]);

        $result = $operation->delete($request);

        $this->assertIsArray($result);
        $this->assertTrue($result['deleted']);

        $pdo = $this->getConnection();

        // Verify operation status is CANCELLED
        $stmt = $pdo->prepare(
            "SELECT operation_status, cancelled_date FROM ntt_bper.t_policy_operation WHERE id = :id"
        );
        $stmt->execute([':id' => $this->operationId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'Operation record must still exist (soft-delete)');
        $this->assertSame('CANCELLED', $row['operation_status']);
        $this->assertNotNull($row['cancelled_date']);

        // Verify draft is removed
        $stmt = $pdo->prepare(
            "SELECT id FROM ntt_bper.t_policy_operation_draft WHERE id = :id"
        );
        $stmt->execute([':id' => $this->draftId]);
        $this->assertFalse($stmt->fetch(), 'Draft must be hard-deleted');

        // Verify docs are removed
        $stmt = $pdo->prepare(
            "SELECT id FROM ntt_bper.t_int_policy_operation_docs WHERE t_policy_operation_draft_id = :id"
        );
        $stmt->execute([':id' => $this->draftId]);
        $this->assertFalse($stmt->fetch(), 'Docs must be hard-deleted');

        // Mark as already cleaned up so tearDown does not fail
        $this->draftId = null;
    }

    /**
     * I-FA-02: getOperations() returns only non-CANCELLED records.
     */
    public function testGetOperationsExcludesCancelledRecords(): void
    {
        $pdo = $this->getConnection();

        // Insert a second operation that is CANCELLED
        $stmt = $pdo->prepare(
            "INSERT INTO ntt_bper.t_policy_operation
                 (t_param_operation_type_id, operation_status, bper_policy_number, company_operation_id,
                  company_code, company_policy_number, premium, sent_date,
                  user_abi, user_agency_code, user_cab, iban,
                  customer_ndg, fiscal_code, fiscal_code_lgrp, code_rapporto, product_code)
             VALUES
                 (:type_id, 'CANCELLED', :bper_policy, 'TEST_FA_CANCELLED_OP',
                  'TEST_CO', 'TEST_CPN', 0.00, NOW(),
                  'TSABI', 'TSAGC', 'TSCAB', 'TEST_IBAN',
                  'TEST_NDG', 'TEST_FC', 'TEST_FCLGRP', 'TEST_CR', 'TEST_PC')
             RETURNING id"
        );
        $stmt->execute([
            ':type_id'     => $this->operationTypeId,
            ':bper_policy' => self::TEST_BPER_POLICY_NUMBER . '_CANCELLED',
        ]);
        $cancelledId = (int) $stmt->fetchColumn();

        try {
            $operation = new ForceAnnulment();
            $request   = new AjaxRequest('GET', '', []);
            $rows      = $operation->getOperations($request);

            $this->assertIsArray($rows);

            // The PENDING test operation must appear
            $ids = array_column($rows, 'id');
            $this->assertContains($this->operationId, $ids);

            // The CANCELLED operation must NOT appear
            $this->assertNotContains($cancelledId, $ids);
        } finally {
            $pdo->prepare("DELETE FROM ntt_bper.t_policy_operation WHERE id = :id")
                ->execute([':id' => $cancelledId]);
        }
    }

    /**
     * I-FA-03: delete() with non-existent id throws RuntimeException.
     */
    public function testDeleteNonExistentIdThrowsRuntimeException(): void
    {
        $operation = new ForceAnnulment();
        $request   = new AjaxRequest('POST', '', ['id' => '999999999']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operazione non trovata');

        $operation->delete($request);
    }

    /**
     * I-FA-04: Verify audit log record exists after successful delete.
     */
    public function testAuditLogRecordExistsAfterDelete(): void
    {
        $operation = new ForceAnnulment();
        $request   = new AjaxRequest('POST', '', ['id' => (string) $this->operationId]);

        $operation->delete($request);

        // Manually log as AjaxResponseHelper::success() would in a real request
        $logger = new OperationAuditLogger();
        $logger->log($operation->getName(), $request->params, $operation->getCurrentUserId());

        $pdo  = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, operation_name, payload
               FROM public.operation_audit_log
              WHERE operation_name = :name
              ORDER BY created_at DESC
              LIMIT 1"
        );
        $stmt->execute([':name' => $operation->getName()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'Audit log record must exist after delete');
        $this->assertSame('forceAnnulment', $row['operation_name']);

        $payload = json_decode($row['payload'], true);
        $this->assertIsArray($payload);
        $this->assertSame((string) $this->operationId, $payload['id']);

        // Mark draft/operation already removed by delete(); tearDown should not double-delete
        $this->draftId = null;
    }
}
