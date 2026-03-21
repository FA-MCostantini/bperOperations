<?php declare(strict_types=1);
namespace FirstAdvisory\FAWill\model\Operations;

use Throwable;
use TraitTryQuery;

class ForceAnnulmentRepository {
    use TraitTryQuery;

    /**
     * @return array<int, array<string, mixed>>
     * @throws Throwable
     */
    public function getOperationList(): array {
        // Q-FA-01: large SELECT with JOIN, WHERE status != 'CANCELLED'
        $stmt = $this->tryQuery(
            "SELECT pot.operation_desc AS \"Operazione\"
                  , pot.operation_code AS \"Codice\"
                  , po.operation_status AS \"Stato\"
                  , po.company_code AS \"Compagnia\"
                  , po.company_policy_number AS \"Codice Compagnia\"
                  , po.bper_policy_number AS \"Codice Banca\"
                  , po.premium AS \"Premio\"
                  , po.sent_date AS \"Data invio\"
                  , po.user_abi AS \"ABI\"
                  , po.user_agency_code AS \"AGENZIA\"
                  , po.user_cab AS \"CAB\"
                  , po.iban AS \"IBAN\"
                  , po.customer_ndg AS \"NDG\"
                  , po.fiscal_code AS \"Cod. fisc.\"
                  , po.fiscal_code_lgrp AS \"LGRP\"
                  , po.code_rapporto AS \"Codice Rapporto\"
                  , po.product_code AS \"Codice Prodotto\"
                  , po.id
               FROM ntt_bper.t_policy_operation po
              INNER JOIN ntt_bper.t_param_operation_type pot
                 ON po.t_param_operation_type_id = pot.id
              WHERE po.operation_status NOT IN ('CANCELLED', 'COMPLETED')"
        );
        if ($stmt === null) {
            return [];
        }
        return $this->getQueryRecords($stmt) ?: [];
    }

    /**
     * @param int $id
     * @return array<string, mixed>|false
     * @throws Throwable
     */
    public function getOperationData(int $id): array|false {
        // Q-FA-02
        $stmt = $this->tryQuery(
            "SELECT bper_policy_number, company_operation_id FROM ntt_bper.t_policy_operation WHERE id = :id_to_delete",
            [':id_to_delete' => $id]
        );
        if ($stmt === null) {
            return false;
        }
        return $this->getQueryRecord($stmt);
    }

    /**
     * @param string $bperPolicyNumber
     * @param string $companyOperationId
     * @return void
     * @throws Throwable
     */
    public function deleteOperation(string $bperPolicyNumber, string $companyOperationId): void {
        // Q-FA-03, Q-FA-04, Q-FA-05 in atomic transaction via stack
        $params = [':bper_policy_number' => $bperPolicyNumber, ':company_operation_id' => $companyOperationId];

        // Q-FA-03: Soft-delete
        $this->addQueryInStack(
            "UPDATE ntt_bper.t_policy_operation SET operation_status = 'CANCELLED', cancelled_date = NOW() WHERE bper_policy_number = :bper_policy_number AND company_operation_id = :company_operation_id",
            $params
        );

        // Q-FA-04: Hard-delete docs
        $this->addQueryInStack(
            "DELETE FROM ntt_bper.t_int_policy_operation_docs doc USING ntt_bper.t_policy_operation_draft draft JOIN ntt_bper.t_policy_operation op ON draft.policy_operation_id = op.id WHERE doc.t_policy_operation_draft_id = draft.id AND op.bper_policy_number = :bper_policy_number AND op.company_operation_id = :company_operation_id",
            $params
        );

        // Q-FA-05: Hard-delete draft
        $this->addQueryInStack(
            "DELETE FROM ntt_bper.t_policy_operation_draft WHERE policy_operation_id = (SELECT id FROM ntt_bper.t_policy_operation WHERE bper_policy_number = :bper_policy_number AND company_operation_id = :company_operation_id) AND bper_policy_number = :bper_policy_number",
            [':bper_policy_number' => $bperPolicyNumber, ':company_operation_id' => $companyOperationId]
        );

        $this->tryQueryStack();
    }
}
