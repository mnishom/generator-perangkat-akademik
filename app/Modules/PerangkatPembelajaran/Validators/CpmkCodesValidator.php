<?php

namespace App\Modules\PerangkatPembelajaran\Validators;

use App\Core\Validation\CheckResult;
use App\Core\Validation\ValidationContext;
use App\Core\Validation\ValidatorContract;

/**
 * Setiap kode CPMK milik MK tercantum, dan tidak ada kode CPMK milik MK lain.
 * Kode yang juga dipakai MK ini, atau dipakai bersama banyak MK, tidak dianggap milik MK lain.
 */
class CpmkCodesValidator implements ValidatorContract
{
    public function validate(ValidationContext $context): array
    {
        $mk = $context->itemInput();
        $own = array_values(array_filter(array_column($mk['cpmk'] ?? [], 'kode')));
        if (! $own) {
            return [];
        }

        $tokens = CodeTokens::fromText($context->text());
        $missing = array_values(array_filter($own, fn ($c) => ! CodeTokens::contains($tokens, $c)));

        $checks = [$missing
            ? CheckResult::fail('cpmk_lengkap', 'Semua CPMK MK tercantum', 'CPMK tidak ditemukan: '.implode(', ', $missing).'.')
            : CheckResult::pass('cpmk_lengkap', 'Semua CPMK MK tercantum', count($own).' CPMK.')];

        // Kode CPMK milik MK lain (unik untuk MK lain, tidak dimiliki MK ini).
        $ownSet = array_flip(array_map([CodeTokens::class, 'normalize'], $own));
        $owners = [];
        foreach ($context->session->items as $item) {
            foreach (($item->input['cpmk'] ?? []) as $c) {
                if ($c['kode'] ?? null) {
                    $owners[CodeTokens::normalize($c['kode'])][$item->ref_code] = $c['kode'];
                }
            }
        }
        $foreign = [];
        foreach ($owners as $norm => $byCourse) {
            if (isset($ownSet[$norm]) || count($byCourse) !== 1 || isset($tokens[$norm]) === false) {
                continue;
            }
            $foreign[] = reset($byCourse).' ('.array_key_first($byCourse).')';
        }

        $checks[] = $foreign
            ? CheckResult::fail('cpmk_asing', 'Tidak ada CPMK MK lain', 'Ditemukan kode CPMK milik MK lain: '.implode(', ', array_slice($foreign, 0, 5)).'.')
            : CheckResult::pass('cpmk_asing', 'Tidak ada CPMK MK lain');

        $reported = $context->report['cpmk_digunakan'] ?? null;
        if (is_array($reported)) {
            $reportedSet = array_flip(array_map(fn ($c) => CodeTokens::normalize((string) $c), $reported));
            $notReported = array_values(array_filter($own, fn ($c) => ! isset($reportedSet[CodeTokens::normalize($c)])));
            if ($notReported) {
                $checks[] = CheckResult::fail('laporan_cpmk', 'Laporan: CPMK digunakan', 'Laporan Claude tidak mencantumkan: '.implode(', ', $notReported).'.');
            }
        }

        return $checks;
    }
}
