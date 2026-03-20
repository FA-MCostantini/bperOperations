<?php declare(strict_types=1);

require_once __DIR__ . '/../BperTestCase.php';

use FirstAdvisory\FAWill\model\Operations\AbstractOperation;
use FirstAdvisory\FAWill\model\Operations\AjaxRequest;
use FirstAdvisory\FAWill\model\Operations\OperationFactory;

/**
 * Stub operation with isEnabled() = false, used for I-ACC-01.
 * Defined in global namespace so OperationFactory can instantiate it via FQCN lookup.
 */
class DisabledTestOperation extends AbstractOperation
{
    public function getName(): string        { return 'disabledTestOperation'; }
    public function getTitle(): string       { return 'Disabled Test Operation'; }
    public function getDescription(): string { return 'Used in AccessControlIntegrationTest'; }
    public function getIcon(): string        { return 'bi-x'; }
    public function getColor(): string       { return 'secondary'; }
    public function getJsPath(): string      { return ''; }
    public function isEnabled(): bool        { return false; }
}

/**
 * Stub operation with isEnabled() = true, used for I-ACC-02.
 */
class EnabledTestOperation extends AbstractOperation
{
    public function getName(): string        { return 'enabledTestOperation'; }
    public function getTitle(): string       { return 'Enabled Test Operation'; }
    public function getDescription(): string { return 'Used in AccessControlIntegrationTest'; }
    public function getIcon(): string        { return 'bi-check'; }
    public function getColor(): string       { return 'success'; }
    public function getJsPath(): string      { return ''; }
    public function isEnabled(): bool        { return true; }
}

/**
 * Integration tests for OperationFactory access control.
 *
 * Test IDs: I-ACC-01 through I-ACC-02
 *
 * These tests exercise OperationFactory::create() with real stub/mock operation classes
 * to verify that the access-control gate (HTTP 403 on disabled operations) works
 * as part of the full bootstrap flow.
 */
class AccessControlIntegrationTest extends BperTestCase
{
    /**
     * I-ACC-01: OperationFactory::create() with a disabled operation throws RuntimeException.
     *
     * OperationFactory also calls http_response_code(403). In the CLI test runner the call
     * is a no-op, but the RuntimeException with the expected message must be thrown.
     *
     * The stub class DisabledTestOperation lives in the global namespace; we inject its
     * short name via the FQCN prefix that OperationFactory applies:
     *   FirstAdvisory\FAWill\model\Operations\<className>
     * Since the stub is registered under the global namespace, we use class_alias to
     * make it resolvable under the expected FQCN.
     */
    public function testCreateDisabledOperationThrows403RuntimeException(): void
    {
        // Make the stub resolvable under the FQCN OperationFactory will construct
        $fqcn = 'FirstAdvisory\\FAWill\\model\\Operations\\DisabledTestOperation';
        if (!class_exists($fqcn)) {
            class_alias(\DisabledTestOperation::class, $fqcn);
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operazione non disponibile');

        OperationFactory::create('DisabledTestOperation');
    }

    /**
     * I-ACC-02: OperationFactory::create() with an enabled operation succeeds.
     *
     * A valid AbstractOperation instance must be returned without any exception.
     */
    public function testCreateEnabledOperationSucceeds(): void
    {
        // Make the stub resolvable under the FQCN OperationFactory will construct
        $fqcn = 'FirstAdvisory\\FAWill\\model\\Operations\\EnabledTestOperation';
        if (!class_exists($fqcn)) {
            class_alias(\EnabledTestOperation::class, $fqcn);
        }

        $operation = OperationFactory::create('EnabledTestOperation');

        $this->assertInstanceOf(AbstractOperation::class, $operation);
        $this->assertTrue($operation->isEnabled());
        $this->assertSame('enabledTestOperation', $operation->getName());
    }

    /**
     * Verify that a real, known operation class (NewRetrievalCode) can be created
     * by the factory without errors.
     */
    public function testCreateRealOperationReturnsCorrectInstance(): void
    {
        $operation = OperationFactory::create('NewRetrievalCode');

        $this->assertInstanceOf(AbstractOperation::class, $operation);
        $this->assertTrue($operation->isEnabled());
        $this->assertSame('newRetrievalCode', $operation->getName());
    }

    /**
     * Verify that requesting a non-existent class name throws RuntimeException.
     */
    public function testCreateNonExistentClassThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Classe operazione non trovata');

        OperationFactory::create('NonExistentOperation_TEST_XYZ');
    }
}
