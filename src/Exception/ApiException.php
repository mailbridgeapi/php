<?php
declare(strict_types=1);

namespace MailBridgeAPI\Exception;

use RuntimeException;
use Throwable;

final class ApiException extends RuntimeException
{
    /** @var int */
    private $statusCode;

    /** @var array<string,mixed> */
    private $response;

    /** @param array<string,mixed> $response */
    public function __construct(string $message, int $statusCode = 0, array $response = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->response = $response;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string,mixed> */
    public function getResponse(): array
    {
        return $this->response;
    }

    public function getRequestId(): ?string
    {
        $value = $this->response['meta']['request_id'] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }
}

