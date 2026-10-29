<?php

namespace App\Modules\PerangkatPembelajaran\Validators;

use App\Core\Validation\CheckResult;
use App\Core\Validation\ValidationContext;
use App\Core\Validation\ValidatorContract;

/**
 * Pemeriksaan isi laporan RPS dari Claude (lapis 1): bobot 100% dan konsisten, setiap CPMK punya Sub-CPMK.
 */
class RpsReportValidator implements ValidatorContract
{
    public function validate(ValidationContext $context): array
    {
        $r = $context->report;
        if (! $r) {
            return [];
        }

        $checks = [];
        $total = $r['total_bobot'] ?? null;
        $sum = WeightComponents::total($r['komponen_penilaian'] ?? null);
        $bobotOk = ($r['bobot_konsisten'] ?? null) === true && is_numeric($total) && abs((float) $total - 100) < 0.01
            && ($sum === null || abs($sum - 100) < 0.01);
        $checks[] = $bobotOk
            ? CheckResult::pass('bobot', 'Total bobot penilaian 100% dan konsisten')
            : CheckResult::fail('bobot', 'Total bobot penilaian 100% dan konsisten', 'Laporan: total bobot '.var_export($total, true)
                .($sum !== null ? ", jumlah komponen {$sum}%" : '').', bobot_konsisten='.var_export($r['bobot_konsisten'] ?? null, true).'.');

        $noSub = array_filter((array) ($r['cpmk_tanpa_subcpmk'] ?? []));
        $checks[] = $noSub
            ? CheckResult::fail('subcpmk', 'Setiap CPMK memiliki Sub-CPMK', 'Tanpa Sub-CPMK: '.implode(', ', $noSub).'.')
            : CheckResult::pass('subcpmk', 'Setiap CPMK memiliki Sub-CPMK');

        $pv = (int) ($r['pustaka_perlu_verifikasi'] ?? 0);
        if ($pv > 0) {
            $checks[] = CheckResult::pass('pustaka', 'Pustaka perlu verifikasi', "{$pv} pustaka ditandai \"perlu verifikasi\" — wajib dicek dosen.");
        }

        return $checks;
    }
}
