<?php

namespace App\Core\Modules;

/**
 * Satu langkah dalam Job: satu panggilan generate yang (biasanya) menghasilkan satu dokumen.
 * Disimpan sebagai array di generation_tasks.steps agar Job dapat dilanjutkan.
 */
final class JobStep
{
    /**
     * @param  string|null  $docType  jenis dokumen yang dihasilkan; null untuk langkah antara (mis. bagian Silabus)
     * @param  string[]  $inputRoles  peran berkas yang diunggah ke container bila container baru
     * @param  string|null  $outputFilename  nama berkas keluaran yang wajib dipakai Claude
     * @param  bool  $collectsOutput  apakah berkas keluaran langkah ini disimpan sebagai dokumen
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $prompt,
        public readonly ?string $docType = null,
        public readonly array $inputRoles = [],
        public readonly ?string $outputFilename = null,
        public readonly bool $collectsOutput = true,
        public readonly string $system = '',
        public readonly array $meta = [],
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'prompt' => $this->prompt,
            'doc_type' => $this->docType,
            'input_roles' => $this->inputRoles,
            'output_filename' => $this->outputFilename,
            'collects_output' => $this->collectsOutput,
            'system' => $this->system,
            'meta' => $this->meta,
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self(
            key: $a['key'],
            label: $a['label'] ?? $a['key'],
            prompt: $a['prompt'],
            docType: $a['doc_type'] ?? null,
            inputRoles: $a['input_roles'] ?? [],
            outputFilename: $a['output_filename'] ?? null,
            collectsOutput: $a['collects_output'] ?? true,
            system: $a['system'] ?? '',
            meta: $a['meta'] ?? [],
        );
    }
}
