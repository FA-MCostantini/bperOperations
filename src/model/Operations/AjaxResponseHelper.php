<?php declare(strict_types=1);

namespace FirstAdvisory\FAWill\model\Operations;

use Throwable;

/**
 * Gestisce input/output delle chiamate Ajax e logging automatico.
 *
 * Responsabilita:
 * - Cattura e incapsula i dati della richiesta (AjaxRequest DTO)
 * - Serializza e invia le risposte JSON
 * - Scrive automaticamente l'audit log su success() quando un'operazione è fornita
 */
class AjaxResponseHelper
{
    private static ?AjaxRequest $currentRequest = null;

    public static function getRequest(): AjaxRequest
    {
        if (self::$currentRequest === null) {
            self::$currentRequest = new AjaxRequest(
                $_SERVER['REQUEST_METHOD'] ?? 'GET',
                $_GET['action'] ?? '',
                array_merge($_GET, $_POST)
            );
        }

        return self::$currentRequest;
    }

    /**
     * @param mixed $data
     */
    public static function success($data = null, ?AbstractOperation $operation = null): void
    {
        if ($operation !== null && self::$currentRequest !== null) {
            $logger = new OperationAuditLogger();
            $logger->log(
                $operation->getName(),
                self::$currentRequest->params,
                $operation->getCurrentUserId()
            );
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public static function error(string $message, ?Throwable $exception = null, int $httpCode = 400): void
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (defined('ENV_IS_DEV') && ENV_IS_DEV && $exception !== null) {
            $response['exception'] = $exception->getMessage() . "\n" . $exception->getTraceAsString();
        }

        http_response_code($httpCode);
        echo json_encode($response);
    }
}
