<?php

namespace App\Core\Validation\Validators;

use App\Core\Validation\CheckResult;
use App\Core\Validation\ValidationContext;
use App\Core\Validation\ValidatorContract;
use ZipArchive;

/**
 * Berkas ada, DOCX valid (ZIP dengan word/document.xml), dan berukuran wajar.
 */
class DocxIntegrityValidator implements ValidatorContract
{
    public function __construct(
        private readonly int $minBytes = 5_000,
        private readonly int $maxBytes = 50_000_000,
        private readonly int $minChars = 200,
    ) {}

    public function validate(ValidationContext $context): array
    {
        $label = 'Berkas DOCX valid';
        $path = $context->absolutePath();

        if (! $path || ! is_file($path)) {
            return [CheckResult::fail('docx_valid', $label, 'Berkas hasil tidak ditemukan.')];
        }

        $size = filesize($path);
        if ($size < $this->minBytes || $size > $this->maxBytes) {
            return [CheckResult::fail('docx_valid', $label, 'Ukuran berkas tidak wajar ('.number_format($size, 0, ',', '.').' bita).')];
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true || $zip->locateName('word/document.xml') === false) {
            return [CheckResult::fail('docx_valid', $label, 'Berkas bukan DOCX yang valid.')];
        }
        $zip->close();

        if (mb_strlen(trim($context->text())) < $this->minChars) {
            return [CheckResult::fail('docx_valid', $label, 'Isi dokumen terlalu sedikit; kemungkinan dokumen kosong.')];
        }

        return [CheckResult::pass('docx_valid', $label, 'Berkas DOCX valid ('.number_format($size / 1024, 0, ',', '.').' KB).')];
    }
}
