<?php declare(strict_types=1);

namespace FirstAdvisory\FAWill\model\Operations;

use Throwable;

/**
 * Rappresenta una risposta Ajax JSON.
 *
 * Responsabilita: struttura dati + serializzazione JSON.
 * NON gestisce aspetti HTTP (response code, header, exit) — quelli
 * sono responsabilita dello script che invia la risposta.
 */
class AjaxResponseHelper
{
    private bool $success;
    /** @var array<string, mixed>|list<array<string, mixed>>|null */
    private $data;
    private string $message;
    private ?string $exceptionDetail;
    private int $httpCode;

    /**
     * @param bool $success
     * @param mixed $data
     * @param string $message
     * @param string|null $exceptionDetail
     * @param int $httpCode
     */
    private function __construct(bool $success, $data, string $message, ?string $exceptionDetail, int $httpCode)
    {
        $this->success = $success;
        $this->data = $data;
        $this->message = $message;
        $this->exceptionDetail = $exceptionDetail;
        $this->httpCode = $httpCode;
    }

    /**
     * @param mixed $data
     * @return self
     */
    public static function success($data = null): self
    {
        return new self(true, $data, '', null, 200);
    }

    /**
     * @param string $message
     * @param Throwable|null $exception
     * @param int $httpCode
     * @return self
     */
    public static function error(string $message, ?Throwable $exception = null, int $httpCode = 400): self
    {
        $exceptionDetail = null;
        if (defined('ENV_IS_DEV') && ENV_IS_DEV && $exception !== null) {
            $exceptionDetail = $exception->getMessage() . "\n" . $exception->getTraceAsString();
        }

        return new self(false, null, $message, $exceptionDetail, $httpCode);
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $response = ['success' => $this->success];

        if ($this->success) {
            $response['data'] = $this->data;
        } else {
            $response['message'] = $this->message;
            if ($this->exceptionDetail !== null) {
                $response['exception'] = $this->exceptionDetail;
            }
        }

        return $response;
    }

    /**
     * Serializza la risposta in JSON.
     */
    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}
