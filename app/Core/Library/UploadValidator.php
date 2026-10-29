<?php

namespace App\Core\Library;

use App\Services\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Validasi tipe (ekstensi + isi berkas) dan ukuran unggahan.
 */
class UploadValidator
{
    public function __construct(private readonly Settings $settings) {}

    /** @return string|null pesan galat, atau null bila valid */
    public function check(UploadedFile $file, array $allowed): ?string
    {
        $ext = Str::lower($file->getClientOriginalExtension());
        $maxMb = (int) $this->settings->get('max_upload_mb', 20);

        if (! in_array($ext, $allowed, true)) {
            return 'Jenis berkas harus '.strtoupper(implode('/', $allowed)).'.';
        }
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            return "Ukuran berkas melebihi batas {$maxMb} MB.";
        }

        $path = $file->getRealPath();
        $valid = match ($ext) {
            'pdf' => str_starts_with((string) file_get_contents($path, false, null, 0, 5), '%PDF-'),
            'docx' => $this->ooxml($path, 'word/document.xml'),
            'pptx' => $this->ooxml($path, 'ppt/presentation.xml'),
            'xlsx' => $this->ooxml($path, 'xl/workbook.xml'),
            default => true,
        };

        return $valid ? null : 'Isi berkas tidak sesuai dengan format '.strtoupper($ext).' atau berkas rusak.';
    }

    private function ooxml(string $path, string $entry): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        $ok = $zip->locateName($entry) !== false;
        $zip->close();

        return $ok;
    }
}
