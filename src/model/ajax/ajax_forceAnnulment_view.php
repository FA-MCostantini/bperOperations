<?php declare(strict_types=1);
require_once __DIR__ . '/../../../lib/autoloader.php';
header('Content-Type: application/json');

use FirstAdvisory\FAWill\model\Operations\{AjaxResponseHelper, ForceAnnulment, OperationFactory};

try {
    $request = AjaxResponseHelper::getRequest();
    /** @var ForceAnnulment $operation */
    $operation = OperationFactory::create('ForceAnnulment');

    switch ($request->action) {
        case 'tabella':
            AjaxResponseHelper::success($operation->getOperations());
            break;
        default:
            AjaxResponseHelper::error('Azione non valida');
    }
} catch (\Throwable $e) {
    AjaxResponseHelper::error($e->getMessage(), $e);
}
