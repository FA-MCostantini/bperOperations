<?php declare(strict_types=1);
namespace FirstAdvisory\FAWill\model\Operations;

class NewRetrievalCodeRepository {
    use \TraitTryQuery;

    /**
     * @return list<string>
     */
    public function searchPolicyNumber(string $searchTerm): array {
        // Q-NRC-01: SELECT bper_policy_number FROM ntt_bper.v_policy WHERE bper_policy_number LIKE :search_term LIMIT 10
        // Append '%' to searchTerm
        $stmt = $this->tryQuery(
            "SELECT bper_policy_number FROM ntt_bper.v_policy WHERE bper_policy_number LIKE :search_term LIMIT 10",
            [':search_term' => $searchTerm . '%']
        );
        if ($stmt === null) {
            return [];
        }
        $records = $this->getQueryRecords($stmt);
        return array_column($records ?: [], 'bper_policy_number');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getExistingCodes(string $bperContractNumber): array {
        // Q-NRC-02
        $stmt = $this->tryQuery(
            "SELECT insert_date, code, operation_type_code FROM ntt_bper.t_ath_policy_auth_code WHERE bper_contract_number = :bper_contract_number ORDER BY insert_date DESC, operation_type_code",
            [':bper_contract_number' => $bperContractNumber]
        );
        if ($stmt === null) {
            return [];
        }
        return $this->getQueryRecords($stmt) ?: [];
    }

    public function calculateNextCode(string $codePrefix): ?int {
        // Q-NRC-03
        $stmt = $this->tryQuery(
            "SELECT MAX(CAST(RIGHT(code, 1) AS INTEGER)) AS max_n FROM ntt_bper.t_ath_policy_auth_code WHERE code LIKE :code_prefix",
            [':code_prefix' => $codePrefix . '%']
        );
        if ($stmt === null) {
            return null;
        }
        $record = $this->getQueryRecord($stmt);
        return $record && $record['max_n'] !== null ? (int)$record['max_n'] : null;
    }

    public function insertCode(string $code, string $bperContractNumber, string $operationTypeCode): void {
        // Q-NRC-04
        $this->tryQuery(
            "INSERT INTO ntt_bper.t_ath_policy_auth_code (code, insert_date, bper_contract_number, operation_type_code) VALUES (:code, NOW(), :bper_contract_number, :operation_type_code) ON CONFLICT DO NOTHING",
            [':code' => $code, ':bper_contract_number' => $bperContractNumber, ':operation_type_code' => $operationTypeCode]
        );
    }
}
