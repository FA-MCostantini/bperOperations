import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests',
    timeout: 30000,
    retries: 0,
    use: {
        baseURL: 'http://localhost:8080',
        trace: 'on-first-retry',
    },
    webServer: {
        command: 'php -S localhost:8080 -t ../../',
        port: 8080,
        reuseExistingServer: true,
        cwd: __dirname,
    },
    globalSetup: './global-setup.ts',
    globalTeardown: './global-teardown.ts',
});
