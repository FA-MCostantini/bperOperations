<?php declare(strict_types=1);

namespace FirstAdvisory\FAWill\model\Operations;

class OperationFactory
{
    /**
     * @throws \RuntimeException se l'operazione è disabilitata (HTTP 403)
     */
    public static function create(string $className): AbstractOperation
    {
        $fqcn = 'FirstAdvisory\\FAWill\\model\\Operations\\' . $className;

        if (!class_exists($fqcn)) {
            throw new \RuntimeException("Classe operazione non trovata: {$className}");
        }

        /** @var AbstractOperation $operation */
        $operation = new $fqcn();

        if (!$operation->isEnabled()) {
            http_response_code(403);
            throw new \RuntimeException('Operazione non disponibile');
        }

        return $operation;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function discoverAll(): array
    {
        $operationsDir = __DIR__;
        $files = glob($operationsDir . '/*.php');
        if ($files === false) {
            return [];
        }
        $result = [];

        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fqcn = 'FirstAdvisory\\FAWill\\model\\Operations\\' . $className;

            if (!class_exists($fqcn)) {
                continue;
            }

            $implements = class_implements($fqcn);
            if ($implements === false || !in_array(OperationInterface::class, $implements, true)) {
                continue;
            }

            $ref = new \ReflectionClass($fqcn);
            if ($ref->isAbstract() || $ref->isInterface()) {
                continue;
            }

            /** @var OperationInterface $instance */
            $instance = new $fqcn();

            if (!$instance->isVisible()) {
                continue;
            }

            $result[] = [
                'title'       => $instance->getTitle(),
                'description' => $instance->getDescription(),
                'icon'        => $instance->getIcon(),
                'color'       => $instance->getColor(),
                'jsPath'      => $instance->getJsPath(),
                'enabled'     => $instance->isEnabled(),
            ];
        }

        return $result;
    }
}
