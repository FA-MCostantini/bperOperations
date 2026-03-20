<?php declare(strict_types=1);

require_once __DIR__ . '/../lib/autoloader.php';

class BperTestCase extends \PHPUnit\Framework\TestCase
{
    protected const TEST_PREFIX = 'TEST_';
    protected ?\PDO $pdo = null;

    protected function getConnection(): \PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                ENV_DB_HOST,
                ENV_DB_PORT,
                ENV_DB_DATABABE
            );
            $this->pdo = new \PDO($dsn, ENV_DB_USER, ENV_DB_PASSWORD, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
        }
        return $this->pdo;
    }

    protected function insertFixture(string $table, array $data): void
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($data);
    }

    protected function cleanupFixtures(string $table, string $column, string $pattern): void
    {
        $sql = "DELETE FROM {$table} WHERE {$column} LIKE :pattern";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([':pattern' => $pattern]);
    }

    protected function cleanupAuditLog(): void
    {
        $this->getConnection()->exec(
            "DELETE FROM public.operation_audit_log WHERE operation_name LIKE 'TEST_%'
             OR operation_name IN ('forceAnnulment', 'newRetrievalCode', 'resetDocumentState')"
        );
    }
}
