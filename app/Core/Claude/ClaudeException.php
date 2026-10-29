<?php

namespace App\Core\Claude;

use RuntimeException;

/**
 * Galat dari Claude API. `transient` = boleh dicoba ulang otomatis (429, 5xx, 529, koneksi).
 */
class ClaudeException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $transient = false,
        public readonly ?int $status = null,
        public readonly ?string $errorType = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status ?? 0);
    }

    public function isContainerExpired(): bool
    {
        return ! $this->transient && $this->status === 400 && str_contains(strtolower($this->getMessage()), 'container');
    }
}
