<?php declare(strict_types=1);

namespace FirstAdvisory\FAWill\model\Operations;

use FirstAdvisory\FAWill\model\libs\cls_Auth;
use Throwable;
use TraitTryQuery;

class OperationAuditLogger
{
    use TraitTryQuery;
    private static ?cls_Auth $auth = null;

    /**
     * @param string $operationName
     * @param array<string, mixed> $payload
     * @throws Throwable
     */
    public function log(string $operationName, array $payload): void
    {
        $userId = self::getAuth()->id;
        $this->tryQuery(
            "INSERT INTO operation_audit_log
                    (operation_name, payload, user_id, created_at)
             VALUES (:operation_name, :payload::jsonb, :user_id, NOW())",
            [
                ':operation_name' => $operationName,
                ':payload'        => json_encode($payload) ?: '{}',
                ':user_id'        => $userId,
            ]
        );
    }

    private static function getAuth():cls_Auth {
        if (null === self::$auth) {
            self::$auth = unserialize($_SESSION['logged_user'] ?? '') ?: new cls_Auth();;
        }
        return self::$auth;
    }
}
