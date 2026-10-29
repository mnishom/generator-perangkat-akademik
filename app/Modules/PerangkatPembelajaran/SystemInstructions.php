<?php

namespace App\Modules\PerangkatPembelajaran;

/**
 * Instruksi sistem tetap Modul Perangkat Pembelajaran (tidak dapat diedit dari UI).
 * Menjaga akurasi tanpa mengubah maksud prompt pemilik. Dicatat di docs/DECISIONS.md (D-013).
 */
class SystemInstructions
{
    /**
     * @param  array<string, string>  $files  peran => nama berkas di container
     * @param  array<string, ?string>  $identity  data identitas sesi (null/kosong = tidak tersedia)
     */
    public static function forCourse(array $files, array $identity, string $rpsFilename, ?string $kontrakFilename): string
    {
        $parts = [
            self::intro(),
            self::fileRoles($files),
            <<<'TXT'
## Akurasi data
- Kode dan rumusan CPL serta CPMK, SKS, dan semester WAJIB sama persis dengan "Data mata kuliah" yang diberikan pada pesan pengguna (data ini sudah diverifikasi prodi). Jangan mengambil data MK lain dari dokumen kurikulum, dan jangan mencantumkan kode CPMK milik MK lain.
- Struktur pertemuan menyesuaikan jenis MK dan template: MK praktikum menekankan kegiatan praktik; MK teori+praktikum memadukan keduanya; magang/PKL dan tugas akhir mengikuti karakteristiknya dan tidak dipaksakan ke pola 16 pertemuan bila template atau contoh RPS menentukan lain. Bila mengikuti pola 16 pertemuan, sediakan minggu UTS dan UAS.
- Gunakan model/metode pembelajaran yang berpusat pada mahasiswa (mis. PjBL, PBL, case method, diskusi kelompok, praktik terbimbing).
- Setiap CPMK diturunkan menjadi Sub-CPMK yang terukur. Total bobot penilaian = 100% dan konsisten antara rencana mingguan dan rekap penilaian.
- Bila deskripsi MK tidak tersedia pada data MK, susun deskripsi singkat yang diturunkan HANYA dari CPMK dan bahan kajian MK tersebut.
- Bila data MK memuat bobot CPMK→CPL, gunakan sebagai acuan proporsi penilaian dan "Peta Kontribusi (CPL)".
- Pustaka: utamakan pustaka dari data MK/dokumen kurikulum. Pustaka tambahan yang Anda usulkan wajib nyata dan ditandai "(perlu verifikasi)" di dokumen; jangan mengarang judul, penulis, atau tahun.
TXT,
            self::identity($identity),
            <<<'TXT'
## Kontrak Perkuliahan
- Kontrak Perkuliahan WAJIB memakai komponen dan bobot penilaian, jadwal/rencana pertemuan, dan pustaka yang SAMA dengan RPS yang Anda susun dalam percakapan ini.
TXT,
            self::output(),
            self::filenames(array_filter(['RPS' => $rpsFilename, 'Kontrak Perkuliahan' => $kontrakFilename])),
            <<<'TXT'
## Laporan pemeriksaan (wajib)
Akhiri setiap jawaban dengan SATU blok ```json berisi laporan pemeriksaan sesuai skema yang diminta pada pesan pengguna. Isi dengan jujur berdasarkan dokumen yang benar-benar Anda hasilkan.
TXT,
        ];

        return implode("\n\n", $parts);
    }

    public static function forSilabus(array $files, array $identity, string $filename): string
    {
        return implode("\n\n", [
            self::intro(),
            self::fileRoles($files),
            <<<'TXT'
## Akurasi data
- Gunakan daftar dan data mata kuliah yang diberikan pada pesan pengguna (sudah diverifikasi prodi) sebagai sumber utama: kode MK, nama MK, SKS, semester, CPL, CPMK, bahan kajian, dan deskripsi wajib sama persis.
- SEMUA mata kuliah dalam daftar wajib tercantum di dokumen Silabus, diurutkan per semester. Jangan menambah MK yang tidak ada di daftar.
- Data yang tidak tersedia jangan dikarang; biarkan kosong atau bertitik-titik sesuai template.
- Pustaka tambahan yang Anda usulkan wajib nyata dan ditandai "(perlu verifikasi)".
- Bila deskripsi MK tidak tersedia pada data MK, susun deskripsi singkat yang diturunkan HANYA dari CPMK dan bahan kajian MK tersebut.
- MK peminatan dicantumkan satu kali pada semesternya dengan keterangan peminatannya.
TXT,
            self::identity($identity),
            self::output(),
            self::filenames(['Silabus' => $filename]),
            <<<'TXT'
## Struktur Silabus
- Bagian sampul dan halaman pengesahan template muncul SEKALI di awal dokumen.
- Blok isian per mata kuliah pada template (identitas MK, CPL, CPMK, deskripsi, bahan kajian, referensi, komponen penilaian) digandakan untuk SETIAP mata kuliah, berurutan per semester, dengan format yang sama persis.
- Komponen penilaian: pilih dari komponen yang disediakan template yang relevan dengan karakter MK.

## Penyusunan bertahap
Silabus disusun bertahap per kelompok mata kuliah dalam beberapa pesan. Simpan dokumen kerja di `/tmp/kerja/` agar dapat dilanjutkan pada pesan berikutnya. Hanya pada tahap terakhir simpan dokumen final ke `$OUTPUT_DIR`.

## Laporan pemeriksaan (wajib pada tahap terakhir)
Pada tahap terakhir, akhiri jawaban dengan SATU blok ```json berisi laporan pemeriksaan sesuai skema yang diminta.
TXT,
        ]);
    }

    private static function intro(): string
    {
        return 'Anda membantu Program Studi menyusun perangkat pembelajaran resmi (dokumen Word) berbasis kurikulum OBE. Gunakan skill docx dan code execution untuk membaca berkas masukan dan menghasilkan dokumen. Kerjakan dengan teliti dan dalam Bahasa Indonesia baku.';
    }

    private static function fileRoles(array $files): string
    {
        $lines = ['## Peran setiap berkas'];
        if (isset($files['kurikulum'])) {
            $lines[] = "- \"{$files['kurikulum']}\" = dokumen kurikulum OBE prodi: SUMBER DATA.";
        }
        if (isset($files['contoh_rps'])) {
            $lines[] = "- \"{$files['contoh_rps']}\" = contoh RPS final: ACUAN GAYA, KELENGKAPAN, DAN KEDALAMAN ISI SAJA. Isi materi, Sub-CPMK, dan pustakanya TIDAK BOLEH disalin ke MK lain.";
        }
        foreach (['template_rps' => 'RPS', 'template_kontrak' => 'Kontrak Perkuliahan', 'template_silabus' => 'Silabus'] as $role => $label) {
            if (isset($files[$role])) {
                $lines[] = "- \"{$files[$role]}\" = template {$label}: FORMAT WAJIB. Isi template tersebut (salin lalu isi berkas template) tanpa mengubah tata letak, kop, logo, warna, jenis/ukuran huruf, ukuran kolom, maupun urutan bagian. Jangan membuat dokumen baru dari nol.";
            }
        }

        return implode("\n", $lines);
    }

    private static function identity(array $identity): string
    {
        $labels = [
            'nama_fakultas' => 'Nama fakultas', 'nama_prodi' => 'Nama program studi',
            'nama_kaprodi' => 'Nama Ketua Program Studi', 'nama_dekan' => 'Nama Dekan',
        ];
        $lines = ['## Data identitas'];
        foreach ($labels as $key => $label) {
            $value = trim((string) ($identity[$key] ?? ''));
            $lines[] = $value !== ''
                ? "- {$label}: {$value} (pakai persis seperti ini)."
                : "- {$label}: TIDAK TERSEDIA, biarkan kosong/bertitik-titik sesuai template, jangan dikarang.";
        }
        $lines[] = '- Data identitas lain yang tidak tersedia (nama dosen pengampu, NIP/NIDN, nomor dokumen, tanda tangan, tanggal pengesahan) dibiarkan kosong atau bertitik-titik sesuai template.';

        return implode("\n", $lines);
    }

    private static function output(): string
    {
        return <<<'TXT'
## Isi template
- Ganti SEMUA teks petunjuk dan penanda isian template dengan isi sebenarnya, mis. "[tuliskan …]", "[kode mata kuliah]", "(Petunjuk: …)", "Tulis pustaka utama", "Mahasiswa mampu….", serta sel tabel "….". Hapus baris/sel yang tidak terpakai (mis. "CPLN", "CPMKN", "SC-N", "dst").
- Baris CONTOH bawaan template (mis. contoh Sub-CPMK, contoh minggu, contoh rubrik, contoh tugas) bukan milik MK ini: ganti dengan isi MK ini, jangan dibiarkan.
- Pertahankan catatan/keterangan baku template (mis. paragraf "Keterangan:" dan catatan bertanda "*)"), kecuali yang jelas merupakan petunjuk pengisian.
- Bila template menulis bobot SKS sebagai "T=, P=, L=, S=", isi sesuai rincian SKS pada data MK.
- Tambahkan baris tabel bila diperlukan (mis. untuk setiap CPMK, Sub-CPMK, atau minggu) dengan format yang sama dengan baris template.

## Berkas keluaran
- Simpan setiap dokumen final ke direktori `$OUTPUT_DIR` (hanya berkas di sana yang diterima aplikasi). Simpan hanya dokumen final, bukan berkas sementara.
- Setelah menyimpan, buka kembali dokumen dan periksa: tidak ada teks penanda template yang belum terisi (mis. "[isi …]", "<…>", "………") selain bagian identitas yang memang dikosongkan.
TXT;
    }

    private static function filenames(array $names): string
    {
        $lines = ['## Nama berkas yang wajib dipakai'];
        foreach ($names as $label => $name) {
            $lines[] = "- {$label}: `{$name}`";
        }

        return implode("\n", $lines);
    }
}
