<?php

namespace Foundry\Exceptions;

use Exception;

class RefundException extends Exception
{
    protected array $metadata;

    public function __construct(string $message, array $metadata = [], int $code = 0, ?Exception $previous = null)
    {
        $this->metadata = $metadata;
        parent::__construct($message, $code, $previous);
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if this is a "not supported" error type.
     */
    public function isNotSupported(): bool
    {
        return ($this->metadata['error_type'] ?? null) === 'not_supported';
    }
}
