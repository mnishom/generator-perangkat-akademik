<?php

namespace App\Core\Validation;

/**
 * Mengambil blok JSON laporan pemeriksaan (lapis 1) dari jawaban akhir Claude.
 */
class ReportParser
{
    /** Ambil objek JSON terakhir yang valid: utamakan blok ```json, lalu objek {...} terakhir. */
    public static function parse(string $text): ?array
    {
        if (preg_match_all('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $m)) {
            foreach (array_reverse($m[1]) as $candidate) {
                $data = json_decode($candidate, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        // Tanpa pagar kode: objek JSON yang berakhir di '}' terakhir.
        $end = strrpos($text, '}');
        if ($end === false) {
            return null;
        }
        $head = substr($text, 0, $end + 1);
        for ($i = strrpos($head, '{'); $i !== false; $i = $i > 0 ? strrpos(substr($head, 0, $i), '{') : false) {
            $data = json_decode(substr($head, $i), true);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Periksa kelengkapan kolom laporan terhadap skema modul.
     *
     * @return CheckResult[]
     */
    public static function checkAgainstSchema(?array $report, array $schema): array
    {
        if ($report === null) {
            return [new CheckResult('laporan', 'Laporan pemeriksaan Claude', false, 'Claude tidak menyertakan blok JSON laporan pemeriksaan.', 'claude')];
        }

        $missing = array_values(array_diff(array_keys($schema), array_keys($report)));

        return [new CheckResult(
            'laporan',
            'Laporan pemeriksaan Claude',
            $missing === [],
            $missing === [] ? 'Laporan lengkap.' : 'Kolom laporan tidak ada: '.implode(', ', $missing).'.',
            'claude',
        )];
    }
}
