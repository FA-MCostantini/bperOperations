<?php declare(strict_types=1);

use FirstAdvisory\FAWill\model\Operations\AjaxRequest;

/**
 * S-07 — HTTP Method Enforcement
 *
 * Verifies that AjaxRequest correctly captures the HTTP method and that the
 * method property is available for enforcement in operation handlers.
 * Since tests operate at the Operation layer (not the HTTP layer), these tests
 * confirm that the DTO faithfully reflects the method so that controllers can
 * apply GET-vs-POST enforcement rules.
 */
class HttpMethodTest extends BperTestCase
{
    /**
     * S-07: AjaxRequest stores the GET method verbatim.
     */
    public function testS07AjaxRequestCapturesGetMethod(): void
    {
        $request = new AjaxRequest('GET', '', ['id' => '1']);

        $this->assertEquals('GET', $request->method, 'AjaxRequest must preserve GET method');
    }

    /**
     * S-07: AjaxRequest stores the POST method verbatim.
     */
    public function testS07AjaxRequestCapturesPostMethod(): void
    {
        $request = new AjaxRequest('POST', '', ['id' => '1']);

        $this->assertEquals('POST', $request->method, 'AjaxRequest must preserve POST method');
    }

    /**
     * S-07: The method property is read-only (readonly) — assignment must fail.
     *
     * PHP 8.1+ readonly properties throw an Error on re-assignment, ensuring
     * the captured method cannot be tampered with after construction.
     */
    public function testS07AjaxRequestMethodIsReadOnly(): void
    {
        $request = new AjaxRequest('GET', 'search', ['q' => 'test']);

        $this->expectException(\Error::class);

        // Attempt to overwrite the readonly property — must throw
        /** @phpstan-ignore-next-line */
        $request->method = 'POST';
    }

    /**
     * S-07: AjaxRequest stores the action verbatim, unrelated to method.
     */
    public function testS07AjaxRequestCapturesAction(): void
    {
        $request = new AjaxRequest('GET', 'tabella', ['bper_contract_number' => '12345']);

        $this->assertEquals('tabella', $request->action, 'AjaxRequest must preserve action');
        $this->assertEquals('GET', $request->method, 'Method must be GET');
    }

    /**
     * S-07: Method name is preserved exactly — not normalised to uppercase.
     *
     * The operation layer should receive the method as-supplied; any
     * case-normalisation is the caller's responsibility.
     */
    public function testS07AjaxRequestPreservesMethodCaseExactly(): void
    {
        $requestLower = new AjaxRequest('post', '', []);
        $requestUpper = new AjaxRequest('POST', '', []);

        // Strict equality: 'post' != 'POST'
        $this->assertNotEquals(
            $requestLower->method,
            $requestUpper->method,
            'AjaxRequest must not normalise method case'
        );
    }
}
