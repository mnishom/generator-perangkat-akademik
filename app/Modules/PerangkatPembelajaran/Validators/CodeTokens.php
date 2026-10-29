<?php

namespace App\Modules\PerangkatPembelajaran\Validators;

/**
 * Pencocokan kode (CPMK, MK) yang toleran terhadap pemisah: "CPMK-019.1" ≈ "CPMK0191".
 */
class CodeTokens
{
    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /** @return array<string, true> himpunan token kode yang muncul di teks */
    public static function fromText(string $text): array
    {
        preg_match_all('/\b[A-Za-z]{2,}[\s\-_.]?\d[\w.\-]*/u', $text, $m);
        $set = [];
        foreach ($m[0] as $token) {
            $token = rtrim($token, '.-_');
            $set[self::normalize($token)] = true;
            // "CPMK0191.1" (Sub-CPMK) juga menandakan CPMK0191 disebut.
            $parts = explode('.', $token);
            while (count($parts) > 1) {
                array_pop($parts);
                $set[self::normalize(implode('.', $parts))] = true;
            }
        }

        return $set;
    }

    public static function contains(array $set, string $code): bool
    {
        return isset($set[self::normalize($code)]);
    }
}
