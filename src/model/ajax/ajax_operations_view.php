<?php declare(strict_types=1);

require_once __DIR__ . '/../../../lib/autoloader.php';

header('Content-Type: application/json');

use FirstAdvisory\FAWill\model\Operations\{AjaxResponseHelper, OperationFactory};

try {
    $request = AjaxResponseHelper::getRequest();

    switch ($request->action) {
        case 'list':
            $operations = OperationFactory::discoverAll();
            AjaxResponseHelper::success($operations);
            break;
        default:
            AjaxResponseHelper::error('Azione non valida');
    }
} catch (\Throwable $e) {
    AjaxResponseHelper::error($e->getMessage(), $e);
}
