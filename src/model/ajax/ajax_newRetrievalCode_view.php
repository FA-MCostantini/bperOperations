<?php declare(strict_types=1);
require_once __DIR__ . '/../../../lib/autoloader.php';
header('Content-Type: application/json');

use FirstAdvisory\FAWill\model\Operations\{AjaxResponseHelper, NewRetrievalCode, OperationFactory};

try {
    $request = AjaxResponseHelper::getRequest();
    /** @var NewRetrievalCode $operation */
    $operation = OperationFactory::create('NewRetrievalCode');

    switch ($request->action) {
        case 'search':
            AjaxResponseHelper::success($operation->searchPolicy($request));
            break;
        case 'tabella':
            AjaxResponseHelper::success($operation->getExistingCodes($request));
            break;
        case 'calc':
            AjaxResponseHelper::success($operation->calculatePreview($request));
            break;
        default:
            AjaxResponseHelper::error('Azione non valida');
    }
} catch (\Throwable $e) {
    AjaxResponseHelper::error($e->getMessage(), $e);
}
