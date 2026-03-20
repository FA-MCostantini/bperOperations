<?php declare(strict_types=1);
namespace FirstAdvisory\FAWill\model\Operations;

class ForceAnnulment extends AbstractOperation {
    private ForceAnnulmentRepository $repository;

    public function __construct() {
        $this->repository = new ForceAnnulmentRepository();
    }

    public function getName(): string { return 'forceAnnulment'; }
    public function getTitle(): string { return 'Annullamento Forzato'; }
    public function getDescription(): string { return 'Annulla operazioni inserite per errore'; }
    public function getIcon(): string { return 'bi-trash'; }
    public function getColor(): string { return 'danger'; }
    public function getJsPath(): string { return './assets-fa/js/Operations/forceAnnulment.js'; }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOperations(AjaxRequest $request): array {
        return $this->repository->getOperationList();
    }

    /**
     * @return array{deleted: true}
     */
    public function delete(AjaxRequest $request): array {
        $rawId = (string) $request->get('id', '0');
        if (!ctype_digit($rawId) || $rawId === '0') {
            throw new \InvalidArgumentException('ID operazione non valido');
        }
        $id = (int) $rawId;
        $data = $this->repository->getOperationData($id);
        if ($data === false) {
            throw new \RuntimeException('Operazione non trovata');
        }
        $this->repository->deleteOperation($data['bper_policy_number'], $data['company_operation_id']);
        return ['deleted' => true];
    }
}
