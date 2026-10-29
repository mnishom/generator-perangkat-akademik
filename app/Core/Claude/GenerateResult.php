<?php

namespace App\Core\Claude;

use Carbon\CarbonImmutable;

final class GenerateResult
{
    /**
     * @param  array  $messages  percakapan lengkap termasuk jawaban assistant terakhir
     * @param  array<int, array{file_id: string, filename: ?string}>  $outputFiles  berkas yang dihasilkan di $OUTPUT_DIR
     * @param  array{input_tokens: int, output_tokens: int, cache_read_input_tokens: int, cache_creation_input_tokens: int}  $usage  akumulasi seluruh permintaan
     */
    public function __construct(
        public readonly array $messages,
        public readonly string $text,
        public readonly string $stopReason,
        public readonly ?string $containerId,
        public readonly ?CarbonImmutable $containerExpiresAt,
        public readonly array $outputFiles,
        public readonly array $usage,
        public readonly int $requests = 1,
    ) {}

    public function inputTokens(): int
    {
        return (int) ($this->usage['input_tokens'] ?? 0)
            + (int) ($this->usage['cache_read_input_tokens'] ?? 0)
            + (int) ($this->usage['cache_creation_input_tokens'] ?? 0);
    }

    public function outputTokens(): int
    {
        return (int) ($this->usage['output_tokens'] ?? 0);
    }
}
