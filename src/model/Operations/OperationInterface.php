<?php declare(strict_types=1);

namespace FirstAdvisory\FAWill\model\Operations;

interface OperationInterface
{
    public function getName(): string;

    public function getTitle(): string;

    public function getDescription(): string;

    public function getIcon(): string;

    public function getColor(): string;

    public function getJsPath(): string;

    public function isVisible(): bool;

    public function isEnabled(): bool;
}
