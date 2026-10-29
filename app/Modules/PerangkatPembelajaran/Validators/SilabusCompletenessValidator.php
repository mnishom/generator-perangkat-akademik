<?php

namespace App\Modules\PerangkatPembelajaran\Validators;

use App\Core\Validation\CheckResult;
use App\Core\Validation\DocxText;
use App\Core\Validation\ValidationContext;
use App\Core\Validation\ValidatorContract;

/**
 * Setiap kode MK dari data terverifikasi tercantum di Silabus.
 */
class SilabusCompletenessValidator implements ValidatorContract
{
    public function validate(ValidationContext $context): array
    {
        $codes = $context->session->items->pluck('ref_code')->filter()->values()->all();
        $text = $context->text();
        $tokens = CodeTokens::fromText($text);
        $norm = DocxText::normalize($text);

        $missing = array_values(array_filter($codes, fn ($c) => ! CodeTokens::contains($tokens, $c) && ! str_contains($norm, mb_strtolower($c))));
        $checks = [$missing
            ? CheckResult::fail('silabus_lengkap', 'Semua MK tercantum di Silabus', count($missing).' dari '.count($codes).' MK tidak ditemukan: '.implode(', ', array_slice($missing, 0, 10)).(count($missing) > 10 ? ', …' : '').'.')
            : CheckResult::pass('silabus_lengkap', 'Semua MK tercantum di Silabus', count($codes).' MK.')];

        $r = $context->report ?? [];
        $skipped = array_filter((array) ($r['mk_terlewat'] ?? []));
        if ($skipped) {
            $checks[] = CheckResult::fail('silabus_laporan', 'Laporan: MK terlewat', 'Claude melaporkan MK terlewat: '.implode(', ', $skipped).'.');
        }
        if (isset($r['jumlah_mk_di_dokumen']) && (int) $r['jumlah_mk_di_dokumen'] !== count($codes)) {
            $checks[] = CheckResult::fail('silabus_jumlah', 'Laporan: jumlah MK', "Laporan menyebut {$r['jumlah_mk_di_dokumen']} MK di dokumen, data terverifikasi berisi ".count($codes).' MK.');
        }

        return $checks;
    }
}
