<?php declare(strict_types=1);
namespace FirstAdvisory\FAWill\model\Operations;

use InvalidArgumentException;
use Throwable;

class ResetDocumentState extends AbstractOperation {
    private ResetDocumentStateRepository $repository;

    public function __construct() {
        $this->repository = new ResetDocumentStateRepository();
    }

    public function getName(): string { return 'resetDocumentState'; }
    public function getTitle(): string { return 'Cambio Stato Documento'; }
    public function getDescription(): string { return 'Forza lo stato dei documenti da PENDING a ERROR'; }
    public function getIcon(): string { return 'bi-arrow-repeat'; }
    public function getColor(): string { return 'warning'; }
    public function getJsPath(): string { return './assets-fa/js/Operations/resetDocumentState.js'; }

    /**
     * @return array<int, array<string, mixed>>
     * @throws Throwable
     */
    public function getDrafts(): array {
        return $this->repository->getDraftList();
    }

    /**
     * @param AjaxRequest $request
     * @return array{updated: true}
     * @throws Throwable
     */
    public function updateStatus(AjaxRequest $request): array {
        $id = (int) $request->get('id', 0);
        if ($id <= 0) {
            throw new InvalidArgumentException('ID draft non valido');
        }
        $this->repository->updateDocumentStatus($id);
        return ['updated' => true];
    }
}
