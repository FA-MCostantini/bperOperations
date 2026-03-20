<?php declare(strict_types=1);
require_once __DIR__ . '/../../../lib/autoloader.php';
header('Content-Type: application/json');

use FirstAdvisory\FAWill\model\Operations\{AjaxResponseHelper, OperationFactory};

try {
    $request = AjaxResponseHelper::getRequest();
    $operation = OperationFactory::create('NewRetrievalCode');
    $result = $operation->insert($request);
    AjaxResponseHelper::success($result, $operation);  // log automatico
} catch (\Throwable $e) {
    AjaxResponseHelper::error($e->getMessage(), $e);
}
