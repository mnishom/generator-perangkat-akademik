<?php

namespace App\Core\Validation;

final class CheckResult
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $passed,
        public readonly string $message = '',
        public readonly string $source = 'aplikasi', // aplikasi | claude
    ) {}

    public static function pass(string $key, string $label, string $message = ''): self
    {
        return new self($key, $label, true, $message);
    }

    public static function fail(string $key, string $label, string $message): self
    {
        return new self($key, $label, false, $message);
    }

    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'passed' => $this->passed, 'message' => $this->message, 'source' => $this->source];
    }
}
