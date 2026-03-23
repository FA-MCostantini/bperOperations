<?php declare(strict_types=1);
require_once __DIR__ . '/../../../lib/autoloader.php';
header('Content-Type: application/json');

use FirstAdvisory\FAWill\model\Operations\{AjaxResponseHelper, OperationFactory, ResetDocumentState};

try {
    $request = AjaxResponseHelper::getRequest();
    /** @var ResetDocumentState $operation */
    $operation = OperationFactory::create('ResetDocumentState');

    switch ($request->action) {
        case 'tabella':
            AjaxResponseHelper::success($operation->getDrafts());
            break;
        default:
            AjaxResponseHelper::error('Azione non valida');
    }
} catch (\Throwable $e) {
    AjaxResponseHelper::error($e->getMessage(), $e);
}
