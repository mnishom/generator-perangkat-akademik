<?php

namespace App\Core\Claude;

final class GenerateRequest
{
    /**
     * @param  array  $messages  seluruh percakapan (format Messages API)
     * @param  string[]  $skills  skill Anthropic, mis. ['docx', 'pdf']
     * @param  string|null  $containerId  container yang dipakai ulang
     */
    public function __construct(
        public readonly string $model,
        public readonly array $messages,
        public readonly string $system = '',
        public readonly array $skills = ['docx'],
        public readonly ?string $containerId = null,
        public readonly ?int $maxTokens = null,
        public readonly ?string $effort = null,
    ) {}

    public function withContainer(?string $containerId): self
    {
        return new self($this->model, $this->messages, $this->system, $this->skills, $containerId, $this->maxTokens, $this->effort);
    }
}
