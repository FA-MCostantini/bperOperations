<?php declare(strict_types=1);

namespace FirstAdvisory\FAWill\model\Operations;

class AjaxRequest
{
    public readonly string $method;
    public readonly string $action;
    /** @var array<string, mixed> */
    public readonly array $params;

    /**
     * @param array<string, mixed> $params
     */
    public function __construct(string $method, string $action, array $params)
    {
        $this->method = $method;
        $this->action = $action;
        $this->params = $params;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}
