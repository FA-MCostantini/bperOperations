<?php declare(strict_types=1);
namespace FirstAdvisory\FAWill\model\Operations;

use PDO;
use Throwable;
use TraitTryQuery;

class ResetDocumentStateRepository {
    use TraitTryQuery;

    /**
     * @return array<int, array<string, mixed>>
     * @throws Throwable
     */
    public function getDraftList(): array {
        // Q-RDS-01: Complex query with 4 JOINs + GROUP BY + COUNT FILTER
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
                  , COUNT(*) FILTER (WHERE tapod.download_status = 'PENDING') AS \"Doc. PENDING\"
                  , COUNT(*) FILTER (WHERE tapod.download_status = 'ERROR') AS \"Doc. ERROR\"
                  , tpod.id
               FROM ntt_bper.t_policy_operation_draft tpod
              INNER JOIN ntt_bper.t_ath_policy_operation_docs tapod
                 ON tpod.id = tapod.t_policy_operation_draft_id
              INNER JOIN ntt_bper.t_policy_operation po
                 ON po.id = tpod.policy_operation_id
              INNER JOIN ntt_bper.t_param_operation_type pot
                 ON po.t_param_operation_type_id = pot.id
              WHERE tapod.download_status IN ('PENDING', 'ERROR')
                AND po.operation_status NOT IN ('CANCELLED', 'COMPLETED')
              GROUP BY tpod.id
                     , pot.operation_desc, pot.operation_code
                     , po.operation_status, po.company_code
                     , po.company_policy_number, po.bper_policy_number
                     , po.premium, po.sent_date
                     , po.user_abi, po.user_agency_code, po.user_cab
                     , po.iban, po.customer_ndg, po.fiscal_code
                     , po.fiscal_code_lgrp, po.code_rapporto, po.product_code"
        );
        if ($stmt === null) {
            return [];
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param int $draftId
     * @return void
     * @throws Throwable
     */
    public function updateDocumentStatus(int $draftId): void {
        // Q-RDS-02
        $this->tryQuery(
            "UPDATE ntt_bper.t_ath_policy_operation_docs tapod
                SET download_status = 'ERROR'
               FROM ntt_bper.t_policy_operation_draft tpod
              WHERE tpod.id = tapod.t_policy_operation_draft_id
                AND tpod.id = :id
                AND tapod.download_status = 'PENDING'",
            [':id' => $draftId]
        );
    }
}
