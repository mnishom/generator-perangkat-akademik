<?php

namespace App\Modules\PerangkatPembelajaran\Validators;

use App\Core\Validation\CheckResult;
use App\Core\Validation\DocxText;
use App\Core\Validation\ValidationContext;
use App\Core\Validation\ValidatorContract;

/**
 * Kode MK, nama MK, dan SKS muncul dengan benar di dokumen; laporan Claude konsisten.
 */
class CourseIdentityValidator implements ValidatorContract
{
    public function __construct(private readonly bool $checkSks = true) {}

    public function validate(ValidationContext $context): array
    {
        $mk = $context->itemInput();
        if (! $mk) {
            return [];
        }

        $text = $context->text();
        $norm = DocxText::normalize($text);
        $checks = [];

        $codeOk = CodeTokens::contains(CodeTokens::fromText($text), $mk['kode']) || str_contains($norm, mb_strtolower($mk['kode']));
        $checks[] = $codeOk
            ? CheckResult::pass('kode_mk', 'Kode MK tercantum', $mk['kode'])
            : CheckResult::fail('kode_mk', 'Kode MK tercantum', "Kode MK {$mk['kode']} tidak ditemukan di dokumen.");

        $nameOk = str_contains($norm, DocxText::normalize($mk['nama']));
        $checks[] = $nameOk
            ? CheckResult::pass('nama_mk', 'Nama MK tercantum', $mk['nama'])
            : CheckResult::fail('nama_mk', 'Nama MK tercantum', "Nama MK \"{$mk['nama']}\" tidak ditemukan persis di dokumen.");

        if ($this->checkSks && ($sks = $mk['sks']['total'] ?? null)) {
            $pattern = '/(\b'.$sks.'\s*\(?\s*sks\b)|(\bsks\b[^0-9\n]{0,40}\b'.$sks.'\b)|(\bbobot\b[^0-9\n]{0,40}\b'.$sks.'\b)/iu';
            $checks[] = preg_match($pattern, $text)
                ? CheckResult::pass('sks', 'SKS sesuai', "{$sks} SKS")
                : CheckResult::fail('sks', 'SKS sesuai', "Bobot {$sks} SKS tidak ditemukan dengan jelas di dokumen.");
        }

        $report = $context->report ?? [];
        if (isset($report['kode_mk']) && CodeTokens::normalize((string) $report['kode_mk']) !== CodeTokens::normalize($mk['kode'])) {
            $checks[] = CheckResult::fail('laporan_kode', 'Laporan: kode MK', "Laporan Claude menyebut kode {$report['kode_mk']}, seharusnya {$mk['kode']}.");
        }
        if (isset($report['sks']) && is_numeric($report['sks']) && (int) $report['sks'] !== (int) ($mk['sks']['total'] ?? 0)) {
            $checks[] = CheckResult::fail('laporan_sks', 'Laporan: SKS', "Laporan Claude menyebut {$report['sks']} SKS, seharusnya {$mk['sks']['total']}.");
        }

        return $checks;
    }
}
