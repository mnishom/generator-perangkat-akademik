<?php

namespace App\Core\Validation\Validators;

use App\Core\Validation\CheckResult;
use App\Core\Validation\ValidationContext;
use App\Core\Validation\ValidatorContract;

/**
 * Tidak tersisa teks penanda template yang belum terisi, kecuali pada bagian
 * identitas yang memang boleh dikosongkan (nama dosen, NIP, tanda tangan, dst.).
 */
class LeftoverPlaceholderValidator implements ValidatorContract
{
    /** Kata kunci baris identitas yang boleh tetap bertitik-titik. */
    public const IDENTITY_WORDS = [
        'dosen', 'pengampu', 'nama', 'nip', 'nidn', 'nik', 'tanda tangan', 'ttd', 'ketua', 'kaprodi', 'dekan',
        'koordinator', 'gkm', 'penjaminan', 'tanggal', 'tgl', 'nomor', 'no.', 'kode dokumen', 'revisi', 'disahkan',
        'diperiksa', 'disetujui', 'disusun', 'mahasiswa', 'kelas', 'ruang', 'waktu', 'hari', 'telp', 'email', 'paraf',
    ];

    public function validate(ValidationContext $context): array
    {
        $label = 'Tidak ada penanda template tersisa';
        $text = $context->text();
        if ($text === '') {
            return [];
        }

        $found = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }
            $explicit = preg_match('/\[(isi|diisi|tuliskan|masukkan|ketik)[^\]]*\]|<(isi|diisi)[^>]*>|\{\{[^}]*\}\}|lorem ipsum|\bXXX+\b/iu', $trim);
            $dotted = preg_match('/(…{3,}|\.{6,}|_{6,})/u', $trim);

            if ($explicit || ($dotted && ! $this->isIdentityLine($trim))) {
                $found[] = mb_strimwidth(preg_replace('/\s+/u', ' ', $trim), 0, 90, '…');
            }
        }

        if (! $found) {
            return [CheckResult::pass('placeholder', $label)];
        }

        $sample = implode('" · "', array_slice(array_unique($found), 0, 3));

        return [CheckResult::fail('placeholder', $label, count($found).' bagian tampak belum terisi, mis.: "'.$sample.'".')];
    }

    private function isIdentityLine(string $line): bool
    {
        $lower = mb_strtolower($line);
        foreach (self::IDENTITY_WORDS as $w) {
            if (str_contains($lower, $w)) {
                return true;
            }
        }

        // Baris yang seluruhnya titik/garis (tempat tanda tangan) dianggap identitas.
        return preg_match('/^[\s.…_:\-()]+$/u', $line) === 1;
    }
}
