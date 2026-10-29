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
        'fakultas', 'program studi', 'kode', 'edisi', 'otorisasi', 'pengembang', 'penanggung jawab', 'jabatan',
    ];

    /** Teks petunjuk/penanda template yang wajib sudah diganti isi. */
    public const EXPLICIT = '/\\[\\s*(isi|isikan|diisi|tuliskan|tulis|masukkan|ketik|susun|minimal|kode mata kuliah|sks mata kuliah|semester ganjil|nama program studi|nama mata kuliah)\\b[^\\]]*\\]?'
        .'|\\(\\s*petunjuk[^)]*\\)?'
        .'|^\\s*petunjuk pengisian'
        .'|\\btulis (materi|pustaka)\\b'
        .'|\\bmampu\\s*(…|\\.{3,})'
        .'|<(isi|diisi)[^>]*>|\\{\\{[^}]*\\}\\}|lorem ipsum|\\bXXX+\\b/iu';

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
            $explicit = preg_match(self::EXPLICIT, $trim);
            // Titik-titik panjang, atau sel tabel yang hanya berisi titik/elipsis (mis. "….").
            $dotted = preg_match('/(…{3,}|\.{6,}|_{6,})|(^|\t)\s*[.…]{2,}\s*(\t|$)/u', $trim);

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

        // Data yang selalu tersedia (nama MK, tahun akademik) tidak boleh dibiarkan bertitik-titik.
        if (preg_match('/(mata kuliah|tahun akademik)\s*[.…_]{3,}/u', $lower)) {
            return false;
        }
        foreach (self::IDENTITY_WORDS as $w) {
            if (str_contains($lower, $w)) {
                return true;
            }
        }

        // Tempat tanda tangan: baris berisi titik/garis di dalam tanda kurung, mis. "(………)".
        return preg_match('/^[\s.…_:\-()]+$/u', $line) === 1 && str_contains($line, '(');
    }
}
