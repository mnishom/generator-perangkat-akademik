<?php

namespace App\Modules\PerangkatPembelajaran\Validators;

use App\Core\Validation\CheckResult;
use App\Core\Validation\ValidationContext;
use App\Core\Validation\ValidatorContract;

/**
 * Komponen dan bobot penilaian di laporan Kontrak sama dengan di laporan RPS MK yang sama.
 */
class KontrakConsistencyValidator implements ValidatorContract
{
    public function validate(ValidationContext $context): array
    {
        $label = 'Komponen & bobot sama dengan RPS';
        $kontrak = WeightComponents::normalize($context->report['komponen_penilaian'] ?? null);
        $rpsDoc = $context->sibling('rps');
        $rps = WeightComponents::normalize($rpsDoc?->report['komponen_penilaian'] ?? null);

        $checks = [];
        if (! $rpsDoc) {
            $checks[] = CheckResult::fail('kontrak_rps', $label, 'RPS untuk MK ini belum ada.');
        } elseif (! $rps || ! $kontrak) {
            $checks[] = CheckResult::fail('kontrak_rps', $label, 'Laporan '.(! $rps ? 'RPS' : 'Kontrak').' tidak memuat komponen_penilaian sehingga tidak dapat dibandingkan.');
        } else {
            $diff = [];
            foreach (array_unique(array_merge(array_keys($rps), array_keys($kontrak))) as $name) {
                $a = $rps[$name] ?? null;
                $b = $kontrak[$name] ?? null;
                if ($a === null || $b === null || abs($a - $b) > 0.01) {
                    $diff[] = "{$name}: RPS ".($a === null ? '—' : $a.'%').' vs Kontrak '.($b === null ? '—' : $b.'%');
                }
            }
            $checks[] = $diff
                ? CheckResult::fail('kontrak_rps', $label, 'Berbeda — '.implode('; ', $diff).'.')
                : CheckResult::pass('kontrak_rps', $label, count($rps).' komponen sama.');
        }

        if (($context->report['konsisten_dengan_rps'] ?? null) === false) {
            $checks[] = CheckResult::fail('kontrak_laporan', 'Laporan: konsisten dengan RPS', 'Claude melaporkan Kontrak tidak konsisten dengan RPS.');
        }

        return $checks;
    }
}
