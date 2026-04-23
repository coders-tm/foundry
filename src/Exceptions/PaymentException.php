<?php

namespace Foundry\Exceptions;

use Exception;

class PaymentException extends Exception
{
    protected array $metadata = [];

    public function __construct(string $message = '', array $metadata = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->metadata = $metadata;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function context(): array
    {
        return array_merge([
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ], $this->metadata);
    }
}
