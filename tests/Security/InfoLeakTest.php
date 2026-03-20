<?php declare(strict_types=1);

use FirstAdvisory\FAWill\model\Operations\AjaxResponseHelper;

/**
 * S-10 — Information Leak Prevention
 *
 * Verifies that AjaxResponseHelper::error() returns a well-formed error
 * structure and, when ENV_IS_DEV is false, does not expose exception details
 * (message, stack trace) in the response payload.
 *
 * Because ENV_IS_DEV is a compile-time constant defined by env_settings.php,
 * the tests capture the echoed JSON output via output buffering and parse it.
 */
class InfoLeakTest extends BperTestCase
{
    /**
     * S-10: error() must always return a JSON object with success=false and a
     * message field, regardless of the dev/prod mode.
     */
    public function testS10ErrorResponseHasCorrectStructure(): void
    {
        ob_start();
        AjaxResponseHelper::error('Something went wrong');
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded, 'error() must output valid JSON');
        $this->assertArrayHasKey('success', $decoded, 'Response must contain "success" key');
        $this->assertFalse($decoded['success'], '"success" must be false for error responses');
        $this->assertArrayHasKey('message', $decoded, 'Response must contain "message" key');
        $this->assertEquals('Something went wrong', $decoded['message'], '"message" must match provided string');
    }

    /**
     * S-10: When ENV_IS_DEV is false, the response must NOT contain an
     * "exception" key (no stack trace / internal details exposed).
     *
     * If ENV_IS_DEV is true in the current test environment this test confirms
     * the conditional branching logic exists by checking that the key IS present
     * in dev mode — the important contract is the absence in prod mode.
     */
    public function testS10ExceptionKeyAbsentInProductionMode(): void
    {
        $exception = new \RuntimeException('Internal connection failed at line 42');

        ob_start();
        AjaxResponseHelper::error('An error occurred', $exception);
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertIsArray($decoded, 'error() must output valid JSON');

        if (defined('ENV_IS_DEV') && ENV_IS_DEV === true) {
            // In dev mode the exception key is expected — document the behaviour
            $this->assertArrayHasKey(
                'exception',
                $decoded,
                'In dev mode "exception" key must be present (expected behaviour)'
            );
        } else {
            // In production mode no exception details must be exposed
            $this->assertArrayNotHasKey(
                'exception',
                $decoded,
                'In production mode "exception" key must NOT be present to prevent info leak'
            );
            $this->assertStringNotContainsString(
                $exception->getMessage(),
                $output,
                'Raw exception message must not appear in production error response'
            );
            $this->assertStringNotContainsString(
                'Stack trace',
                $output,
                'Stack trace must not appear in production error response'
            );
        }
    }

    /**
     * S-10: error() without an exception must never include the "exception" key
     * regardless of ENV_IS_DEV.
     */
    public function testS10NoExceptionKeyWhenNoExceptionPassed(): void
    {
        ob_start();
        AjaxResponseHelper::error('Validation failed');
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertIsArray($decoded, 'error() must output valid JSON');
        $this->assertArrayNotHasKey(
            'exception',
            $decoded,
            '"exception" key must be absent when no exception object is provided'
        );
    }

    /**
     * S-10: error() output must not contain raw PHP class names or file paths
     * that would reveal internal architecture.
     */
    public function testS10ErrorResponseDoesNotLeakFilePaths(): void
    {
        $exception = new \PDOException('SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "secret_table" does not exist');

        ob_start();
        AjaxResponseHelper::error('Database error', $exception);
        $output = ob_get_clean();

        if (!defined('ENV_IS_DEV') || ENV_IS_DEV !== true) {
            $this->assertStringNotContainsString(
                'secret_table',
                $output,
                'Internal SQL error details must not be exposed in production mode'
            );
        } else {
            // In dev mode, simply assert the JSON is well-formed
            $decoded = json_decode($output, true);
            $this->assertIsArray($decoded, 'error() must output valid JSON even in dev mode');
        }
    }
}
