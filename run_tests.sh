#!/bin/bash
set -e

echo "=== Running DB migrations ==="
docker compose -f tests/docker-compose.yml run --rm phpunit php -r "
    require_once 'lib/env_settings.php';
    \$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', ENV_DB_HOST, ENV_DB_PORT, ENV_DB_DATABABE);
    \$pdo = new PDO(\$dsn, ENV_DB_USER, ENV_DB_PASSWORD);
    \$sql = file_get_contents('tests/migrations/001_create_audit_log.sql');
    \$pdo->exec(\$sql);
    echo 'Migration completed successfully.' . PHP_EOL;
"

echo "=== Running PHPUnit tests ==="
docker compose -f tests/docker-compose.yml run --rm phpunit vendor/bin/phpunit -c tests/phpunit.xml
PHPUNIT_EXIT=$?

echo "=== Running Playwright E2E tests (optional) ==="
if [ -d "tests/e2e" ] && [ -f "tests/e2e/package.json" ]; then
    cd tests/e2e
    npm install --silent 2>/dev/null
    npx playwright test || true
    cd ../..
fi

exit $PHPUNIT_EXIT
