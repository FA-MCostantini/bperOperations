<?php

/**
 * This file is part of the Firstance BPER project.
 *
 * @copyright Firstance srl.
 */

if (!defined('ENV_SETTINGS')) {
    define('ENV_SETTINGS', '1');

    // Environment
    define('ENV_IS_DEV', true);
    define('ENVIRONMENT_NAME', 'development');
    define('ENV_BASE_URL', '');
    define('ENV_SERVER_HOST', 'https://fa-bper.dev.firstance.com');

    // Database
    define('ENV_DB_HOST', 'fa-db');
    define('ENV_DB_PORT', '5432');
    define('ENV_DB_USER', 'postgres');
    define('ENV_DB_PASSWORD', 'local!Passw0rd');
    define('ENV_DB_DATABABE', 'fa-dev-bper');
}
