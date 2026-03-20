<?php declare(strict_types=1);

namespace FirstAdvisory\FAWill\model\Operations;

class OperationAuditLogger
{
    use \TraitTryQuery;

    public function log(string $operationName, array $payload, int $userId): void
    {
        $this->tryQuery(
            "INSERT INTO public.operation_audit_log
                    (operation_name, payload, user_id, created_at)
             VALUES (:operation_name, :payload::jsonb, :user_id, NOW())",
            [
                ':operation_name' => $operationName,
                ':payload'        => json_encode($payload),
                ':user_id'        => $userId,
            ]
        );
    }
}
