<?php declare(strict_types=1);
namespace FirstAdvisory\FAWill\model\Operations;

class NewRetrievalCode extends AbstractOperation {
    private NewRetrievalCodeRepository $repository;

    public function __construct() {
        $this->repository = new NewRetrievalCodeRepository();
    }

    public function getName(): string { return 'newRetrievalCode'; }
    public function getTitle(): string { return 'Inserimento Codice Riscatto'; }
    public function getDescription(): string { return 'Inserisci un nuovo codice di riscatto parziale o totale'; }
    public function getIcon(): string { return 'bi-upc-scan'; }
    public function getColor(): string { return 'primary'; }
    public function getJsPath(): string { return './assets-fa/js/Operations/newRetrievalCode.js'; }

    /**
     * @return list<string>
     */
    public function searchPolicy(AjaxRequest $request): array {
        $q = (string) $request->get('q', '');
        if (strlen($q) < 2) {
            return [];
        }
        return $this->repository->searchPolicyNumber($q);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getExistingCodes(AjaxRequest $request): array {
        return $this->repository->getExistingCodes((string) $request->get('bper_contract_number', ''));
    }

    /**
     * @return array{code: string, next_n: int}
     */
    public function calculatePreview(AjaxRequest $request): array {
        return $this->generateCode(
            (string) $request->get('bper_contract_number', ''),
            (string) $request->get('type', 'T')
        );
    }

    /**
     * @return array{code: string, inserted: true}
     */
    public function insert(AjaxRequest $request): array {
        $contractNumber = (string) $request->get('bper_contract_number', '');
        $type = (string) $request->get('type', 'T');
        $result = $this->generateCode($contractNumber, $type);
        $operationTypeCode = $this->getOperationTypeCode($type);
        $this->repository->insertCode($result['code'], $contractNumber, $operationTypeCode);
        return ['code' => $result['code'], 'inserted' => true];
    }

    private function getOperationTypeCode(string $type): string {
        return match ($type) {
            'P' => '_RISPA',
            'T' => '_RISTO',
            default => throw new \InvalidArgumentException("Tipo non valido: {$type}"),
        };
    }

    /**
     * @return array{code: string, next_n: int}
     */
    private function generateCode(string $contractNumber, string $type): array {
        $prefix = 'R' . $type . $contractNumber;
        $maxN = $this->repository->calculateNextCode($prefix);
        $nextN = $maxN === null ? 1 : $maxN + 1;

        if ($nextN > 9) {
            throw new \RuntimeException('Limite massimo codici raggiunto per questo contratto e tipo');
        }

        return ['code' => $prefix . $nextN, 'next_n' => $nextN];
    }
}
