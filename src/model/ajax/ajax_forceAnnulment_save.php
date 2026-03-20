<?php declare(strict_types=1);
require_once __DIR__ . '/../../../lib/autoloader.php';
header('Content-Type: application/json');

use FirstAdvisory\FAWill\model\Operations\{AjaxResponseHelper, OperationFactory};

try {
    $request = AjaxResponseHelper::getRequest();
    /** @var \FirstAdvisory\FAWill\model\Operations\ForceAnnulment $operation */
    $operation = OperationFactory::create('ForceAnnulment');
    $result = $operation->delete($request);
    AjaxResponseHelper::success($result, $operation);  // log automatico
} catch (\Throwable $e) {
    AjaxResponseHelper::error($e->getMessage(), $e);
}
