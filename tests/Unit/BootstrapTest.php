<?php declare(strict_types=1);

require_once __DIR__ . '/../BperTestCase.php';

use FirstAdvisory\FAWill\model\Operations\OperationFactory;
use FirstAdvisory\FAWill\model\Operations\OperationInterface;

/**
 * Unit tests for OperationFactory::discoverAll() bootstrapping.
 *
 * Test IDs: U-BOOT-01, U-BOOT-02, U-BOOT-03
 */
class BootstrapTest extends BperTestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $discovered;

    protected function setUp(): void
    {
        $this->discovered = OperationFactory::discoverAll();
    }

    // -------------------------------------------------------------------------
    // U-BOOT-01: discoverAll() returns a non-empty array
    // -------------------------------------------------------------------------

    /**
     * U-BOOT-01: discoverAll() must return at least one operation
     */
    public function testUBOOT01_DiscoverAllReturnsNonEmptyArray(): void
    {
        $this->assertIsArray($this->discovered);
        $this->assertNotEmpty($this->discovered, 'discoverAll() must discover at least one operation');
    }

    // -------------------------------------------------------------------------
    // U-BOOT-02: Each result has the required keys
    // -------------------------------------------------------------------------

    /**
     * U-BOOT-02: Every entry returned by discoverAll() contains all required presentation keys
     */
    public function testUBOOT02_EachResultHasRequiredKeys(): void
    {
        $requiredKeys = ['title', 'description', 'icon', 'color', 'jsPath', 'enabled'];

        foreach ($this->discovered as $index => $entry) {
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $entry,
                    "Entry #{$index} is missing required key '{$key}'"
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // U-BOOT-03: Results do not contain abstract or interface classes
    // -------------------------------------------------------------------------

    /**
     * U-BOOT-03: discoverAll() must not expose abstract classes or interfaces.
     *
     * We verify this indirectly: every discovered entry must correspond to a concrete,
     * instantiable class that implements OperationInterface. Since discoverAll() returns
     * data maps (not class names), we confirm that the known non-operation files
     * (AbstractOperation, AjaxRequest, AjaxResponseHelper, OperationFactory,
     *  OperationInterface, *Repository classes) produce no entries by checking that
     * the total number of discovered operations equals only the concrete operation classes.
     *
     * Concrete operations in the project: NewRetrievalCode, ForceAnnulment, ResetDocumentState.
     */
    public function testUBOOT03_ResultsDoNotContainAbstractOrInterfaceClasses(): void
    {
        $operationsDir = __DIR__ . '/../../src/model/Operations';
        $files = glob($operationsDir . '/*.php');

        $abstractOrInterfaceCount = 0;
        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fqcn      = 'FirstAdvisory\\FAWill\\model\\Operations\\' . $className;

            if (!class_exists($fqcn) && !interface_exists($fqcn)) {
                continue;
            }

            $ref = new \ReflectionClass($fqcn);
            if ($ref->isAbstract() || $ref->isInterface()) {
                $abstractOrInterfaceCount++;
            }
        }

        // There must be at least one abstract/interface in the directory
        $this->assertGreaterThan(
            0,
            $abstractOrInterfaceCount,
            'Sanity check: the Operations directory must contain abstract/interface classes'
        );

        // The discovered list must not exceed the number of concrete OperationInterface implementations
        $concreteOperationCount = 0;
        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fqcn      = 'FirstAdvisory\\FAWill\\model\\Operations\\' . $className;

            if (!class_exists($fqcn)) {
                continue;
            }

            if (!in_array(OperationInterface::class, class_implements($fqcn), true)) {
                continue;
            }

            $ref = new \ReflectionClass($fqcn);
            if (!$ref->isAbstract() && !$ref->isInterface()) {
                $concreteOperationCount++;
            }
        }

        $this->assertLessThanOrEqual(
            $concreteOperationCount,
            count($this->discovered),
            'discoverAll() must not return more entries than concrete OperationInterface implementations'
        );
    }
}
