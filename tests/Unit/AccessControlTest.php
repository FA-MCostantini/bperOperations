<?php declare(strict_types=1);

require_once __DIR__ . '/../BperTestCase.php';

use FirstAdvisory\FAWill\model\Operations\AbstractOperation;
use FirstAdvisory\FAWill\model\Operations\NewRetrievalCode;
use FirstAdvisory\FAWill\model\Operations\OperationFactory;

/**
 * Unit tests for access-control behaviour of AbstractOperation and OperationFactory.
 *
 * Test IDs: U-ACC-01 through U-ACC-05
 *
 * For U-ACC-03/04/05 we write temporary stub classes into the Operations source
 * directory so that OperationFactory's glob-based discovery picks them up.
 * The stubs are removed in tearDown().
 */
class AccessControlTest extends BperTestCase
{
    /** Absolute paths to stub files created during the test run. */
    private array $stubFiles = [];

    /** Absolute path to the Operations source directory. */
    private string $operationsDir;

    protected function setUp(): void
    {
        $this->operationsDir = realpath(__DIR__ . '/../../src/model/Operations');
    }

    protected function tearDown(): void
    {
        foreach ($this->stubFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->stubFiles = [];
    }

    // -------------------------------------------------------------------------
    // Helper: write a stub operation file to the Operations directory
    // -------------------------------------------------------------------------

    /**
     * Writes a minimal AbstractOperation subclass stub to the Operations directory
     * and registers it for cleanup.
     *
     * @param string $className   Simple class name (no namespace)
     * @param bool   $isEnabled   Return value of isEnabled()
     * @param bool   $isVisible   Return value of isVisible()
     */
    private function writeStubOperation(
        string $className,
        bool   $isEnabled = true,
        bool   $isVisible = true
    ): string {
        $enabledLiteral = $isEnabled ? 'true' : 'false';
        $visibleLiteral = $isVisible ? 'true' : 'false';

        $code = <<<PHP
<?php declare(strict_types=1);
namespace FirstAdvisory\FAWill\model\Operations;
class {$className} extends AbstractOperation {
    public function getName(): string        { return '{$className}'; }
    public function getTitle(): string       { return 'Stub {$className}'; }
    public function getDescription(): string { return 'Test stub'; }
    public function getIcon(): string        { return 'bi-question'; }
    public function getColor(): string       { return 'secondary'; }
    public function getJsPath(): string      { return './stub.js'; }
    public function isEnabled(): bool        { return {$enabledLiteral}; }
    public function isVisible(): bool        { return {$visibleLiteral}; }
}
PHP;

        $filePath = $this->operationsDir . '/' . $className . '.php';
        file_put_contents($filePath, $code);
        $this->stubFiles[] = $filePath;

        // Force PHP to load the class from the new file
        require_once $filePath;

        return $filePath;
    }

    // -------------------------------------------------------------------------
    // U-ACC-01: Default isVisible() returns true
    // -------------------------------------------------------------------------

    /**
     * U-ACC-01: AbstractOperation::isVisible() defaults to true
     */
    public function testUACC01_DefaultIsVisibleReturnsTrue(): void
    {
        $operation = new NewRetrievalCode();
        $this->assertTrue($operation->isVisible());
    }

    // -------------------------------------------------------------------------
    // U-ACC-02: Default isEnabled() returns true
    // -------------------------------------------------------------------------

    /**
     * U-ACC-02: AbstractOperation::isEnabled() defaults to true
     */
    public function testUACC02_DefaultIsEnabledReturnsTrue(): void
    {
        $operation = new NewRetrievalCode();
        $this->assertTrue($operation->isEnabled());
    }

    // -------------------------------------------------------------------------
    // U-ACC-03: OperationFactory::create() throws RuntimeException for disabled ops
    // -------------------------------------------------------------------------

    /**
     * U-ACC-03: OperationFactory::create() throws RuntimeException (HTTP 403) when
     * the operation's isEnabled() returns false
     */
    public function testUACC03_FactoryCreateThrowsWhenOperationIsDisabled(): void
    {
        $this->writeStubOperation('StubDisabledOperation', isEnabled: false, isVisible: true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operazione non disponibile');

        OperationFactory::create('StubDisabledOperation');
    }

    // -------------------------------------------------------------------------
    // U-ACC-04: discoverAll() excludes invisible operations
    // -------------------------------------------------------------------------

    /**
     * U-ACC-04: discoverAll() must not include an operation whose isVisible() is false
     */
    public function testUACC04_DiscoverAllExcludesInvisibleOperations(): void
    {
        $this->writeStubOperation('StubInvisibleOperation', isEnabled: true, isVisible: false);

        $discovered = OperationFactory::discoverAll();

        $titles = array_column($discovered, 'title');
        $this->assertNotContains(
            'Stub StubInvisibleOperation',
            $titles,
            'discoverAll() must not list an operation whose isVisible() returns false'
        );
    }

    // -------------------------------------------------------------------------
    // U-ACC-05: discoverAll() includes disabled-but-visible operations with enabled=false
    // -------------------------------------------------------------------------

    /**
     * U-ACC-05: A visible but disabled operation appears in discoverAll() with enabled=false
     */
    public function testUACC05_DiscoverAllIncludesVisibleDisabledOperationsWithEnabledFalse(): void
    {
        $this->writeStubOperation('StubVisibleDisabledOp', isEnabled: false, isVisible: true);

        $discovered = OperationFactory::discoverAll();

        $matchingEntries = array_filter(
            $discovered,
            fn(array $entry) => $entry['title'] === 'Stub StubVisibleDisabledOp'
        );

        $this->assertNotEmpty(
            $matchingEntries,
            'A visible-but-disabled operation must appear in discoverAll()'
        );

        $entry = reset($matchingEntries);
        $this->assertFalse(
            $entry['enabled'],
            'A disabled-but-visible operation must have enabled=false in discoverAll()'
        );
    }
}
