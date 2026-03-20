<?php declare(strict_types=1);

require_once __DIR__ . '/../BperTestCase.php';

use FirstAdvisory\FAWill\model\Operations\AjaxRequest;
use FirstAdvisory\FAWill\model\Operations\ResetDocumentState;
use FirstAdvisory\FAWill\model\Operations\OperationAuditLogger;

/**
 * Integration tests for ResetDocumentState — full Operation + Repository + DB flow.
 *
 * Test IDs: I-RDS-01 through I-RDS-04
 */
class ResetDocumentStateIntegrationTest extends BperTestCase
{
    private const TEST_BPER_POLICY_NUMBER   = 'TEST_RDS_POLICY';
    private const TEST_OPERATION_TYPE_DESC  = 'TEST_RDS_OP_DESC';
    private const TEST_OPERATION_TYPE_CODE  = 'TEST_RDS_CODE';

    /** @var int|null Inserted t_param_operation_type.id */
    private ?int $operationTypeId = null;

    /** @var int|null Inserted t_policy_operation.id */
    private ?int $operationId = null;

    /** @var int|null Inserted t_policy_operation_draft.id */
    private ?int $draftId = null;

    protected function setUp(): void
    {
        $pdo = $this->getConnection();

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

        // 2. Insert a test policy operation
        $stmt = $pdo->prepare(
            "INSERT INTO ntt_bper.t_policy_operation
                 (t_param_operation_type_id, operation_status, bper_policy_number, company_operation_id,
                  company_code, company_policy_number, premium, sent_date,
                  user_abi, user_agency_code, user_cab, iban,
                  customer_ndg, fiscal_code, fiscal_code_lgrp, code_rapporto, product_code)
             VALUES
                 (:type_id, 'PENDING', :bper_policy, 'TEST_RDS_OP_001',
                  'TEST_CO', 'TEST_CPN', 100.00, NOW(),
                  'TEST_ABI', 'TEST_AGC', 'TEST_CAB', 'TEST_IBAN',
                  'TEST_NDG', 'TEST_FC', 'TEST_FCLGRP', 'TEST_CR', 'TEST_PC')
             RETURNING id"
        );
        $stmt->execute([
            ':type_id'     => $this->operationTypeId,
            ':bper_policy' => self::TEST_BPER_POLICY_NUMBER,
        ]);
        $this->operationId = (int) $stmt->fetchColumn();

        // 3. Insert a draft linked to the operation
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

        // 4. Insert 3 PENDING docs in t_ath_policy_operation_docs
        $insertDoc = $pdo->prepare(
            "INSERT INTO ntt_bper.t_ath_policy_operation_docs
                 (t_policy_operation_draft_id, download_status)
             VALUES (:draft_id, 'PENDING')"
        );
        for ($i = 0; $i < 3; $i++) {
            $insertDoc->execute([':draft_id' => $this->draftId]);
        }
    }

    protected function tearDown(): void
    {
        $pdo = $this->getConnection();

        if ($this->draftId !== null) {
            $pdo->prepare(
                "DELETE FROM ntt_bper.t_ath_policy_operation_docs WHERE t_policy_operation_draft_id = :id"
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
     * I-RDS-01: getDrafts() returns fixture draft with correct PENDING/ERROR counts.
     */
    public function testGetDraftsReturnsCorrectPendingAndErrorCounts(): void
    {
        $operation = new ResetDocumentState();
        $request   = new AjaxRequest('GET', '', []);

        $drafts = $operation->getDrafts($request);

        $this->assertIsArray($drafts);

        // Find our test draft by id
        $testDraft = null;
        foreach ($drafts as $draft) {
            if ((int) $draft['id'] === $this->draftId) {
                $testDraft = $draft;
                break;
            }
        }

        $this->assertNotNull($testDraft, 'Test draft must appear in getDrafts() results');
        $this->assertSame(3, (int) $testDraft['Doc. PENDING'], '3 PENDING docs must be counted');
        $this->assertSame(0, (int) $testDraft['Doc. ERROR'],   '0 ERROR docs must be counted');
    }

    /**
     * I-RDS-02: updateStatus() changes PENDING docs to ERROR for the given draft id.
     */
    public function testUpdateStatusChangesPendingToError(): void
    {
        $operation = new ResetDocumentState();
        $request   = new AjaxRequest('POST', '', ['id' => (string) $this->draftId]);

        $result = $operation->updateStatus($request);

        $this->assertIsArray($result);
        $this->assertTrue($result['updated']);

        $pdo  = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT download_status, COUNT(*) AS cnt
               FROM ntt_bper.t_ath_policy_operation_docs
              WHERE t_policy_operation_draft_id = :id
              GROUP BY download_status"
        );
        $stmt->execute([':id' => $this->draftId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['download_status']] = (int) $row['cnt'];
        }

        $this->assertSame(0, $counts['PENDING'] ?? 0, 'All PENDING docs must be changed to ERROR');
        $this->assertSame(3, $counts['ERROR']   ?? 0, '3 docs must now be in ERROR state');
    }

    /**
     * I-RDS-03: After updateStatus, getDrafts() reflects updated PENDING/ERROR counts.
     */
    public function testGetDraftsReflectsUpdatedCountsAfterStatusChange(): void
    {
        // Perform the update first
        $operation = new ResetDocumentState();
        $request   = new AjaxRequest('POST', '', ['id' => (string) $this->draftId]);
        $operation->updateStatus($request);

        // Now check that getDrafts reflects the new state
        $viewRequest = new AjaxRequest('GET', '', []);
        $drafts      = $operation->getDrafts($viewRequest);

        $testDraft = null;
        foreach ($drafts as $draft) {
            if ((int) $draft['id'] === $this->draftId) {
                $testDraft = $draft;
                break;
            }
        }

        $this->assertNotNull($testDraft, 'Test draft must still appear after status update');
        $this->assertSame(0, (int) $testDraft['Doc. PENDING'], 'No PENDING docs after update');
        $this->assertSame(3, (int) $testDraft['Doc. ERROR'],   '3 ERROR docs after update');
    }

    /**
     * I-RDS-04: Verify audit log record exists after successful updateStatus.
     */
    public function testAuditLogRecordExistsAfterUpdateStatus(): void
    {
        $operation = new ResetDocumentState();
        $request   = new AjaxRequest('POST', '', ['id' => (string) $this->draftId]);

        $operation->updateStatus($request);

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

        $this->assertNotFalse($row, 'Audit log record must exist after updateStatus');
        $this->assertSame('resetDocumentState', $row['operation_name']);

        $payload = json_decode($row['payload'], true);
        $this->assertIsArray($payload);
        $this->assertSame((string) $this->draftId, $payload['id']);
    }
}
