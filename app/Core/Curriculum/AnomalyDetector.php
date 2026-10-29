<?php

namespace App\Core\Curriculum;

/**
 * Menandai otomatis data kurikulum yang mencurigakan agar diperiksa pengguna.
 */
class AnomalyDetector
{
    /**
     * @return array{global: array<int, array{level: string, message: string}>, courses: array<string, array<int, array{level: string, message: string}>>}
     */
    public function detect(array $data): array
    {
        $global = [];
        $courses = [];
        $cplCodes = array_filter(array_column($data['cpl'] ?? [], 'kode'));
        $cplSet = array_flip(array_map('mb_strtoupper', $cplCodes));
        $seen = [];
        $totalSks = 0;

        if (! $cplCodes) {
            $global[] = ['level' => 'warning', 'message' => 'Daftar CPL prodi kosong; pemetaan CPMK ke CPL tidak dapat diperiksa.'];
        }

        foreach ($data['mata_kuliah'] ?? [] as $i => $mk) {
            $key = $mk['kode'] ?: '#'.($i + 1);
            $flags = [];
            $sks = $mk['sks'] ?? [];
            $totalSks += (int) ($sks['total'] ?? 0);

            if (! $mk['kode']) {
                $flags[] = ['level' => 'error', 'message' => 'Kode MK kosong.'];
            } elseif (isset($seen[mb_strtoupper($mk['kode'])])) {
                $flags[] = ['level' => 'error', 'message' => 'Kode MK ganda.'];
            }
            $seen[mb_strtoupper($mk['kode'])] = true;

            if (! $mk['nama']) {
                $flags[] = ['level' => 'error', 'message' => 'Nama MK kosong.'];
            }
            if (! ($sks['total'] ?? null)) {
                $flags[] = ['level' => 'error', 'message' => 'SKS kosong atau nol.'];
            } else {
                $parts = array_filter([$sks['teori'] ?? null, $sks['praktikum'] ?? null, $sks['lapangan'] ?? null, $sks['seminar'] ?? null], fn ($v) => $v !== null);
                if ($parts && array_sum($parts) !== (int) $sks['total']) {
                    $flags[] = ['level' => 'warning', 'message' => 'Rincian SKS (T/P/L/S = '.array_sum($parts).') tidak sama dengan total '.$sks['total'].'.'];
                }
            }
            if (! $mk['semester']) {
                $flags[] = ['level' => 'warning', 'message' => 'Semester belum terisi.'];
            }
            if (! $mk['cpmk']) {
                $flags[] = ['level' => 'error', 'message' => 'MK tanpa CPMK.'];
            }
            if (! $mk['cpl']) {
                $flags[] = ['level' => 'warning', 'message' => 'Tidak ada CPL yang dibebankan.'];
            }
            if (! $mk['bahan_kajian']) {
                $flags[] = ['level' => 'warning', 'message' => 'Bahan kajian kosong.'];
            }

            if ($cplSet) {
                foreach ($mk['cpl'] as $c) {
                    if ($c['kode'] && ! isset($cplSet[mb_strtoupper($c['kode'])])) {
                        $flags[] = ['level' => 'error', 'message' => "CPL {$c['kode']} tidak ada di daftar CPL prodi."];
                    }
                }
                foreach ($mk['cpmk'] as $c) {
                    foreach ($c['cpl'] as $ref) {
                        if (! isset($cplSet[mb_strtoupper($ref)])) {
                            $flags[] = ['level' => 'error', 'message' => "{$c['kode']} mengacu pada CPL {$ref} yang tidak ada."];
                        }
                    }
                }
            }

            $mkCpl = array_flip(array_map('mb_strtoupper', array_column($mk['cpl'], 'kode')));
            $cpmkCodes = [];
            foreach ($mk['cpmk'] as $c) {
                if (! $c['kode']) {
                    $flags[] = ['level' => 'error', 'message' => 'Ada CPMK tanpa kode.'];
                } elseif (isset($cpmkCodes[$c['kode']])) {
                    $flags[] = ['level' => 'warning', 'message' => "Kode CPMK {$c['kode']} ganda dalam MK ini."];
                }
                $cpmkCodes[$c['kode']] = true;
                if (! $c['cpl']) {
                    $flags[] = ['level' => 'warning', 'message' => "{$c['kode']} belum dipetakan ke CPL."];
                } elseif ($mkCpl) {
                    foreach ($c['cpl'] as $ref) {
                        if (! isset($mkCpl[mb_strtoupper($ref)]) && (! $cplSet || isset($cplSet[mb_strtoupper($ref)]))) {
                            $flags[] = ['level' => 'warning', 'message' => "{$c['kode']} dipetakan ke {$ref}, padahal {$ref} tidak dibebankan pada MK ini."];
                        }
                    }
                }
            }

            if ($flags) {
                $courses[$key] = array_merge($courses[$key] ?? [], $flags);
            }
        }

        $declared = $data['prodi']['total_sks'] ?? null;
        $tracks = CurriculumData::sksPerTrack($data['mata_kuliah'] ?? []);
        if (count($tracks) > 1 || array_key_first($tracks) !== 'semua') {
            // Kurikulum berpeminatan: total dihitung per jalur (MK wajib + MK satu peminatan).
            $totalSks = max($tracks);
            foreach ($tracks as $name => $sum) {
                if ($declared && $sum !== (int) $declared) {
                    $global[] = ['level' => 'error', 'message' => "Jumlah SKS jalur peminatan {$name} ({$sum}) tidak sama dengan total SKS pada dokumen kurikulum ({$declared})."];
                }
            }
        } elseif ($declared && $totalSks !== (int) $declared) {
            $global[] = ['level' => 'error', 'message' => "Jumlah SKS seluruh MK ({$totalSks}) tidak sama dengan total SKS pada dokumen kurikulum ({$declared})."];
        }
        foreach ($data['catatan_ekstraksi'] ?? [] as $note) {
            $global[] = ['level' => 'warning', 'message' => 'Catatan ekstraksi: '.$note];
        }
        if (! ($data['mata_kuliah'] ?? [])) {
            $global[] = ['level' => 'error', 'message' => 'Tidak ada mata kuliah yang terbaca.'];
        }

        return ['global' => $global, 'courses' => $courses, 'total_sks' => $totalSks, 'tracks' => $tracks];
    }

    public function errorCount(array $anomalies): int
    {
        $count = count(array_filter($anomalies['global'] ?? [], fn ($f) => $f['level'] === 'error'));
        foreach ($anomalies['courses'] ?? [] as $flags) {
            $count += count(array_filter($flags, fn ($f) => $f['level'] === 'error'));
        }

        return $count;
    }
}
