import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests',
    timeout: 30000,
    retries: 0,
    use: {
        baseURL: 'http://localhost:8081',
        trace: 'on-first-retry',
    },
    webServer: {
        command: 'docker compose -f ../../tests/docker-compose.yml run --rm -p 8081:8081 phpunit php -S 0.0.0.0:8081 -t /app /app/tests/e2e/router.php',
        port: 8081,
        reuseExistingServer: true,
        timeout: 15000,
    },
    // La test page deve stare nella root del progetto per i percorsi relativi degli asset
    globalSetup: './global-setup.ts',
    globalTeardown: './global-teardown.ts',
});
