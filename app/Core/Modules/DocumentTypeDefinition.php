<?php

namespace App\Core\Modules;

/**
 * Jenis dokumen yang dihasilkan modul.
 */
final class DocumentTypeDefinition
{
    /**
     * @param  string[]  $skills  skill Anthropic untuk keluaran (docx, pptx, xlsx, pdf)
     * @param  string  $filenamePattern  pola nama berkas, misalnya `RPS_{kode_mk}_{nama_mk}.docx`
     * @param  string  $scope  `item` (per butir, mis. per MK) atau `session` (satu per sesi)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $skills = ['docx'],
        public readonly string $format = 'docx',
        public readonly ?string $templateRole = null,
        public readonly string $filenamePattern = '{doc_type}.docx',
        public readonly string $scope = 'item',
        public readonly string $shortLabel = '',
    ) {}

    public function isSessionLevel(): bool
    {
        return $this->scope === 'session';
    }

    public function short(): string
    {
        return $this->shortLabel ?: $this->label;
    }
}
