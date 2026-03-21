<?php declare(strict_types=1);
/**
 * Router for PHP built-in web server used in E2E tests.
 * Maps /model/ajax/* requests to /src/model/ajax/* on disk.
 *
 * Usage: php -S 0.0.0.0:8081 -t /app /app/tests/e2e/router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Rewrite /model/ajax/* → /src/model/ajax/*
if (str_starts_with($uri, '/model/ajax/')) {
    $realPath = $_SERVER['DOCUMENT_ROOT'] . '/src' . $uri;
    if (is_file($realPath)) {
        chdir(dirname($realPath));
        require $realPath;
        return true;
    }
    http_response_code(404);
    echo 'Not found: ' . $uri;
    return true;
}

// Let the built-in server handle all other requests (static files, test-page.php, etc.)
return false;
