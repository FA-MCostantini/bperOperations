<?php declare(strict_types=1);

namespace FirstAdvisory\FAWill\model\Operations;

abstract class AbstractOperation implements OperationInterface
{
    use \TraitTryQuery;

    public function isVisible(): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getCurrentUserId(): int
    {
        return 0;
    }
}
