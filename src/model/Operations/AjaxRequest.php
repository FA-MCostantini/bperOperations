<?php declare(strict_types=1);

namespace FirstAdvisory\FAWill\model\Operations;

readonly class AjaxRequest
{
    public string $method;
    public string $action;
    /** @var array<string, mixed> */
    public array $params;

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
