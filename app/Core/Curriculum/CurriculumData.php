<?php

namespace App\Core\Curriculum;

/**
 * Skema dan normalisasi data kurikulum terstruktur (hasil ekstraksi dan suntingan).
 */
class CurriculumData
{
    public const JENIS_MK = ['teori', 'praktikum', 'teori_praktikum', 'magang_pkl', 'tugas_akhir', 'lainnya'];

    public const JENIS_LABEL = [
        'teori' => 'Teori',
        'praktikum' => 'Praktikum',
        'teori_praktikum' => 'Teori + Praktikum',
        'magang_pkl' => 'Magang/PKL',
        'tugas_akhir' => 'Tugas Akhir',
        'lainnya' => 'Lainnya',
    ];

    /** Skema JSON yang diminta dari Claude (ditulis ke prompt ekstraksi). */
    public static function schemaDescription(): string
    {
        return <<<'JSON'
{
  "prodi": {"nama": "string|null", "jenjang": "string|null", "total_sks": "integer|null — total SKS lulus menurut dokumen (satu jalur peminatan)"},
  "catatan_ekstraksi": ["string — data yang ambigu atau berbeda antar-tabel di dokumen, dan pilihan yang Anda ambil"],
  "profil_lulusan": [{"kode": "string", "deskripsi": "string"}],
  "cpl": [{"kode": "string", "rumusan": "string"}],
  "mata_kuliah": [{
    "kode": "string",
    "nama": "string",
    "sks": {"total": "integer", "teori": "integer|null", "praktikum": "integer|null", "lapangan": "integer|null", "seminar": "integer|null"},
    "semester": "integer|null",
    "peminatan": ["nama peminatan/konsentrasi; kosongkan bila MK wajib untuk semua mahasiswa"],
    "prasyarat": ["kode atau nama MK prasyarat"],
    "jenis": "teori|praktikum|teori_praktikum|magang_pkl|tugas_akhir|lainnya",
    "cpl": [{"kode": "string", "rumusan": "string"}],
    "cpmk": [{"kode": "string", "rumusan": "string", "cpl": ["kode CPL"], "bobot_cpl": {"kode CPL": "angka bobot (%) bila tercantum"}}],
    "bahan_kajian": ["kode dan nama bahan kajian, mis. 'BK93 – Etika Profesi'"],
    "deskripsi": "string|null",
    "pustaka": ["string"]
  }]
}
JSON;
    }

    /** Rapikan data agar selalu memiliki struktur dan tipe yang diharapkan. */
    public static function normalize(array $data): array
    {
        $str = fn ($v) => is_scalar($v) ? trim((string) $v) : '';
        $int = fn ($v) => is_numeric($v) ? (int) $v : null;
        $list = fn ($v) => array_values(array_filter(array_map($str, is_array($v) ? $v : ($v ? preg_split('/\R|;/', (string) $v) : [])), fn ($x) => $x !== ''));

        $cpl = fn ($items) => array_values(array_filter(array_map(fn ($c) => [
            'kode' => $str($c['kode'] ?? ''),
            'rumusan' => $str($c['rumusan'] ?? ($c['deskripsi'] ?? '')),
        ], is_array($items) ? $items : []), fn ($c) => $c['kode'] !== '' || $c['rumusan'] !== ''));

        $courses = [];
        foreach (($data['mata_kuliah'] ?? []) as $mk) {
            if (! is_array($mk)) {
                continue;
            }
            $sks = $mk['sks'] ?? [];
            if (! is_array($sks)) {
                $sks = ['total' => $sks];
            }
            $jenis = $str($mk['jenis'] ?? '');
            $course = [
                'kode' => $str($mk['kode'] ?? ''),
                'nama' => $str($mk['nama'] ?? ''),
                'sks' => [
                    'total' => $int($sks['total'] ?? null),
                    'teori' => $int($sks['teori'] ?? null),
                    'praktikum' => $int($sks['praktikum'] ?? null),
                    'lapangan' => $int($sks['lapangan'] ?? null),
                    'seminar' => $int($sks['seminar'] ?? null),
                ],
                'semester' => $int($mk['semester'] ?? null),
                'peminatan' => $list($mk['peminatan'] ?? []),
                'prasyarat' => $list($mk['prasyarat'] ?? []),
                'jenis' => in_array($jenis, self::JENIS_MK, true) ? $jenis : 'lainnya',
                'cpl' => $cpl($mk['cpl'] ?? []),
                'cpmk' => array_values(array_map(fn ($c) => [
                    'kode' => $str($c['kode'] ?? ''),
                    'rumusan' => $str($c['rumusan'] ?? ''),
                    'cpl' => $list($c['cpl'] ?? []) ?: array_keys(self::weights($c['bobot_cpl'] ?? [])),
                    'bobot_cpl' => self::weights($c['bobot_cpl'] ?? []),
                ], array_filter(is_array($mk['cpmk'] ?? null) ? $mk['cpmk'] : [], 'is_array'))),
                'bahan_kajian' => $list($mk['bahan_kajian'] ?? []),
                'deskripsi' => $str($mk['deskripsi'] ?? '') ?: null,
                'pustaka' => $list($mk['pustaka'] ?? []),
            ];

            // MK yang sama (kode dan nama sama) tercantum di beberapa tabel peminatan → gabungkan.
            // Kode sama dengan nama berbeda tetap dipisah agar ditandai "Kode MK ganda".
            $key = mb_strtoupper($course['kode']);
            $sameCourse = $key !== '' && isset($courses[$key])
                && mb_strtolower(preg_replace('/\s+/u', ' ', $courses[$key]['nama'])) === mb_strtolower(preg_replace('/\s+/u', ' ', $course['nama']));
            if ($sameCourse) {
                $courses[$key] = self::mergeCourse($courses[$key], $course);
            } else {
                $courses[$key !== '' && ! isset($courses[$key]) ? $key : '#'.count($courses)] = $course;
            }
        }

        // CPL per MK tidak tertulis eksplisit → turunkan dari pemetaan CPMK→CPL.
        $prodiCpl = collect($cpl($data['cpl'] ?? []))->keyBy(fn ($c) => mb_strtoupper($c['kode']));
        foreach ($courses as &$course) {
            if (! $course['cpl'] && $course['cpmk']) {
                $codes = array_values(array_unique(array_merge(...array_column($course['cpmk'], 'cpl'))));
                $course['cpl'] = array_map(fn ($k) => ['kode' => $k, 'rumusan' => $prodiCpl[mb_strtoupper($k)]['rumusan'] ?? ''], $codes);
            }
        }
        unset($course);
        $courses = array_values($courses);

        usort($courses, fn ($a, $b) => [($a['semester'] ?? 99), $a['kode']] <=> [($b['semester'] ?? 99), $b['kode']]);

        return [
            'prodi' => [
                'nama' => $str($data['prodi']['nama'] ?? '') ?: null,
                'jenjang' => $str($data['prodi']['jenjang'] ?? '') ?: null,
                'total_sks' => $int($data['prodi']['total_sks'] ?? null),
            ],
            'profil_lulusan' => array_values(array_map(fn ($p) => [
                'kode' => $str($p['kode'] ?? ''),
                'deskripsi' => $str($p['deskripsi'] ?? ($p['rumusan'] ?? '')),
            ], array_filter($data['profil_lulusan'] ?? [], 'is_array'))),
            'cpl' => $cpl($data['cpl'] ?? []),
            'catatan_ekstraksi' => $list($data['catatan_ekstraksi'] ?? []),
            'mata_kuliah' => $courses,
        ];
    }

    /** @return array<string, float> kode CPL => bobot */
    private static function weights(mixed $map): array
    {
        $out = [];
        foreach (is_array($map) ? $map : [] as $k => $v) {
            if (is_string($k) && is_numeric(str_replace(['%', ','], ['', '.'], (string) $v))) {
                $out[trim($k)] = (float) str_replace(['%', ','], ['', '.'], (string) $v);
            }
        }

        return $out;
    }

    /** Gabungkan dua entri MK berkode sama: peminatan disatukan, isian kosong dilengkapi. */
    private static function mergeCourse(array $a, array $b): array
    {
        $a['peminatan'] = array_values(array_unique(array_merge($a['peminatan'], $b['peminatan'])));
        foreach (['cpl', 'cpmk', 'bahan_kajian', 'pustaka', 'prasyarat'] as $k) {
            if (! $a[$k] && $b[$k]) {
                $a[$k] = $b[$k];
            }
        }
        $a['deskripsi'] ??= $b['deskripsi'];
        $a['semester'] ??= $b['semester'];

        return $a;
    }

    /**
     * Total SKS per jalur: MK wajib + MK peminatan tersebut. Tanpa peminatan → satu jalur "semua".
     *
     * @return array<string, int>
     */
    public static function sksPerTrack(array $courses): array
    {
        $base = 0;
        $tracks = [];
        foreach ($courses as $mk) {
            $sks = (int) ($mk['sks']['total'] ?? 0);
            if (! ($mk['peminatan'] ?? [])) {
                $base += $sks;

                continue;
            }
            foreach ($mk['peminatan'] as $t) {
                $tracks[$t] = ($tracks[$t] ?? 0) + $sks;
            }
        }

        return $tracks ? array_map(fn ($v) => $v + $base, $tracks) : ['semua' => $base];
    }

    /** Ringkasan satu MK dalam format mudah dibaca untuk prompt ({{data_mk}}). */
    public static function courseToText(array $mk): string
    {
        $sks = $mk['sks'] ?? [];
        $parts = array_filter([
            'T' => $sks['teori'] ?? null, 'P' => $sks['praktikum'] ?? null,
            'L' => $sks['lapangan'] ?? null, 'S' => $sks['seminar'] ?? null,
        ], fn ($v) => $v !== null);
        $sksText = ($sks['total'] ?? '?').' SKS'.($parts ? ' ('.implode(', ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($parts), $parts)).')' : '');

        $lines = [
            "- Kode MK: {$mk['kode']}",
            "- Nama MK: {$mk['nama']}",
            "- Bobot: {$sksText}",
            '- Semester: '.($mk['semester'] ?? '-'),
            '- Peminatan: '.(($mk['peminatan'] ?? []) ? implode(', ', $mk['peminatan']) : 'wajib (semua peminatan)'),
            '- Jenis MK: '.(self::JENIS_LABEL[$mk['jenis'] ?? 'lainnya'] ?? $mk['jenis']).' ('.($mk['jenis'] ?? 'lainnya').')',
            '- MK prasyarat: '.($mk['prasyarat'] ? implode(', ', $mk['prasyarat']) : '-'),
            '- CPL yang dibebankan:',
        ];
        foreach ($mk['cpl'] ?? [] as $c) {
            $lines[] = "  - {$c['kode']}: {$c['rumusan']}";
        }
        $lines[] = '- CPMK:';
        foreach ($mk['cpmk'] ?? [] as $c) {
            $map = array_map(fn ($k) => isset($c['bobot_cpl'][$k]) ? "{$k} ({$c['bobot_cpl'][$k]}%)" : $k, $c['cpl']);
            $lines[] = "  - {$c['kode']}: {$c['rumusan']}".($map ? ' [→ '.implode(', ', $map).']' : '');
        }
        $lines[] = '- Bahan kajian: '.($mk['bahan_kajian'] ? implode('; ', $mk['bahan_kajian']) : '-');
        $lines[] = '- Deskripsi MK: '.($mk['deskripsi'] ?: '-');
        if ($mk['pustaka'] ?? []) {
            $lines[] = '- Pustaka (dari dokumen kurikulum):';
            foreach ($mk['pustaka'] as $p) {
                $lines[] = "  - {$p}";
            }
        }

        return implode("\n", $lines);
    }

    /** Daftar ringkas seluruh MK per semester ({{daftar_mk}}). */
    public static function coursesToList(array $courses): string
    {
        $out = [];
        foreach (collect($courses)->groupBy(fn ($mk) => $mk['semester'] ?? 0)->sortKeys() as $sem => $group) {
            $out[] = $sem ? "Semester {$sem}:" : 'Semester belum ditentukan:';
            foreach ($group as $mk) {
                $cpmk = implode(', ', array_column($mk['cpmk'] ?? [], 'kode'));
                $out[] = "- {$mk['kode']} {$mk['nama']} ({$mk['sks']['total']} SKS; ".(self::JENIS_LABEL[$mk['jenis']] ?? $mk['jenis']).')'
                    .(($mk['peminatan'] ?? []) ? '; peminatan: '.implode(', ', $mk['peminatan']) : '')
                    .($cpmk ? "; CPMK: {$cpmk}" : '');
            }
        }

        return implode("\n", $out);
    }
}
