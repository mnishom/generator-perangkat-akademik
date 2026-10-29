<?php

namespace App\Modules\PerangkatPembelajaran\Validators;

/**
 * Normalisasi daftar komponen penilaian {nama, bobot} dari laporan Claude.
 */
class WeightComponents
{
    /** @return array<string, float> nama dinormalisasi => bobot */
    public static function normalize(mixed $components): array
    {
        $out = [];
        if (! is_array($components)) {
            return $out;
        }
        foreach ($components as $key => $c) {
            if (is_array($c)) {
                $name = $c['nama'] ?? $c['name'] ?? $c['komponen'] ?? null;
                $weight = $c['bobot'] ?? $c['weight'] ?? $c['persen'] ?? null;
            } else {
                [$name, $weight] = [$key, $c];
            }
            if ($name === null || ! is_numeric(str_replace(['%', ','], ['', '.'], (string) $weight))) {
                continue;
            }
            $norm = trim(preg_replace('/\s+/', ' ', preg_replace('/[^\pL\pN ]/u', ' ', mb_strtolower((string) $name))));
            $out[$norm] = ($out[$norm] ?? 0) + (float) str_replace(['%', ','], ['', '.'], (string) $weight);
        }
        ksort($out);

        return $out;
    }

    public static function total(mixed $components): ?float
    {
        $n = self::normalize($components);

        return $n ? array_sum($n) : null;
    }
}
