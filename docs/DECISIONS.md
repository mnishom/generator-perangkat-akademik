# Catatan Keputusan (Decision Log)

Keputusan penting selama pengembangan, terbaru di bawah. Setiap entri: konteks → keputusan → alasan.

## D-001 · Laravel 12, bukan Laravel 11

- **Konteks:** spesifikasi menyebut Laravel 11 untuk PHP ≥ 8.2. Server memakai PHP ≥ 8.2.
- **Keputusan:** memakai **Laravel 12** (PHP ^8.2).
- **Alasan:** Laravel 11 sudah melewati masa dukungan keamanan. `composer audit` pada Laravel 11.57 menemukan 5 advisori, termasuk *critical* (TokenGuard authentication bypass) dan *high* (CRLF injection pada aturan `email`). Perbaikannya hanya tersedia di Laravel ≥ 12.68. Laravel 12 tetap mendukung PHP 8.2, jadi kebutuhan server tidak berubah.

## D-002 · Login hanya Google Workspace, validasi ganda di server

- Kampus memakai Google Workspace (dikonfirmasi pemilik). Fallback OTP/magic link **tidak** dibuat.
- Callback menolak login kecuali **ketiga** syarat terpenuhi: klaim `hd` dari Google sama persis dengan `ALLOWED_EMAIL_DOMAIN`, alamat email berakhiran `@ALLOWED_EMAIL_DOMAIN` (bukan subdomain), dan `email_verified` tidak bernilai false. Parameter `hd` pada URL redirect hanya petunjuk tampilan bagi Google, bukan pengamanan.
- `role` dan `status` tidak masuk `$fillable`, sehingga tidak dapat diubah lewat mass assignment.
- Akun baru berperan `user`. Email yang tercantum di `ADMIN_EMAILS` (.env) menjadi `admin` pada login pertama, untuk bootstrap admin pertama.
- Status `dinonaktifkan`/`ditangguhkan` dengan `status_until` yang sudah lewat dianggap `aktif` (lihat `User::effectiveStatus()`).

## D-003 · Model default `claude-sonnet-5-5`

Pemilik memilih `claude-sonnet-5-5` (generasi Sonnet terbaru, kompatibel dengan code execution, harga sama dengan `claude-sonnet-5`). Opsi kualitas tertinggi: `claude-opus-5-5`.

## D-004 · Penyesuaian integrasi Claude terhadap dokumentasi terbaru

Diperiksa terhadap dokumentasi resmi (Oktober 2026):

- **Files API dan Skills sudah GA**: header `files-api-2025-04-14` dan `skills-2025-10-02` tidak dikirim lagi.
- **Code execution tool**: memakai `code_execution_20260521`. Agent Skills melalui `container.skills` tetap memakai beta `code-execution-2025-08-25` (sesuai panduan Skills). Daftar beta header dapat diatur di config.
- File hasil hanya tertangkap bila ditulis ke `$OUTPUT_DIR`. File tersebut muncul sebagai `content[].file_id` pada blok hasil `bash_code_execution_tool_result`. Instruksi sistem mewajibkan Claude menyimpan keluaran di sana.
- Respons memuat `container.id` dan `container.expires_at` (maksimal 30 hari). Container yang kedaluwarsa menghasilkan error (tidak dipulihkan), sehingga pelaksana Job membuat container baru dan mengunggah ulang masukan bila perlu.
- `stop_reason: "pause_turn"` dilanjutkan dengan mengirim balik isi respons apa adanya (tanpa pesan "lanjutkan"), dengan batas iterasi yang dapat diatur.

## D-005 · Aset frontend (`public/build`) ikut di-commit

Server tidak memiliki Node.js. Aset di-build di lokal (`npm run build`) dan folder `public/build` di-commit, agar deploy cukup dengan `git pull` atau unggah berkas.

## D-006 · Tailwind CSS 4 + `@tailwindcss/vite`

Utilitas komponen (`btn-primary`, `card`, `input`, dll.) didefinisikan dengan `@utility` di `resources/css/app.css`. Warna identitas: `marun-*` (700 = `#6e1b24`) dan `krem-*` (200 = `#e3d3bd`). Mode gelap memakai strategi kelas `.dark`, disimpan di `localStorage`.

## D-007 · Login cepat untuk pengembangan

Route `/dev/login/{user}` hanya dimuat bila `APP_ENV=local` **dan** `DEV_LOGIN=true`. Route ini tidak pernah aktif di produksi.

## D-008 · Arsitektur modular: inti vs modul

- Modul berada di `app/Modules/<Nama>/` dan mengimplementasikan `App\Core\Modules\ModuleContract`. Modul aktif didaftarkan di `config/modules.php`. Menu, halaman modul (`/m/{module}`), panel admin modul, akses, sakelar, kuota, dan model per modul mengikuti daftar ini secara otomatis.
- **Prioritas akses modul:** aturan per user > aturan per prodi > status aktif modul (tabel `modules` dan `module_access`).
- **Pelaksana Job generik** (`GenerationRunner`) tidak mengenal jenis dokumen. Modul mengembalikan `JobPlan[]` dari `planJobs()`. Setiap `JobPlan` berisi langkah (`JobStep`) berurutan yang dijalankan dalam **satu container** dan **satu percakapan**.
- **Tabel `generation_tasks`** (tambahan dari ERD awal): satu baris = satu Job antrean. Tabel ini menyimpan langkah yang sudah dirender, `current_step`, `container_id` + `container_expires_at`, dan percakapan. `container_id` sengaja disimpan di sini, bukan di `generation_items`, karena satu item dapat memiliki lebih dari satu Job (misalnya generate ulang), dan Job tingkat sesi (Silabus) tidak memiliki item.
- **Dapat dilanjutkan:** status disimpan setelah setiap langkah. Job yang terputus (batas waktu PHP, proses dimatikan cPanel) dijalankan ulang oleh antrean dan melanjutkan dari `current_step`. Bila container sudah kedaluwarsa, Job memulai container baru dan mengunggah ulang masukan secara otomatis.
- **Generate ulang / perbaikan otomatis** = menambahkan langkah baru ke task yang sama (`SessionLauncher::appendAndRun`), sehingga berjalan di container dan percakapan yang sama dan menghasilkan versi baru (`version` +1, `parent_id`) tanpa menghapus versi lama.
- **Pemeriksaan di dalam Job** (`GenerationGuard`): status akun, akses modul, sakelar global, sakelar modul, dan kuota diperiksa sebelum setiap panggilan Claude. Bila ditolak, task berstatus `blocked` beserta alasannya.
- **Batas Job bersamaan:** dihitung dari task `running` yang heartbeat-nya kurang dari 25 menit, dengan lock cache. Job yang belum mendapat slot di-*release* 30 detik. Galat sementara (429/5xx/529/koneksi) dicoba ulang dengan backoff eksponensial (maks. 6 kali). Galat permanen langsung menggagalkan task.
- `DB_QUEUE_RETRY_AFTER` default 1500 detik (Job `timeout` 1200 detik), agar Job yang sedang berjalan tidak diambil worker lain.

## D-009 · Arti batas pemakaian

- **Global** dan **per modul** = anggaran total seluruh pengguna. **Per user** = batas pengguna tersebut. Ketiganya berlaku bersamaan.
- Satu "generate" = satu panggilan generate yang tercatat di `ai_usages` (`kind = generate`), yaitu satu langkah Job. Token = input + output + cache read + cache write.
- Estimasi biaya memakai harga di `config/claude.php`: cache read 0,1× dan cache write 1,25× harga input.

## D-010 · Berkas masukan di Files API dikelola per sesi

- Berkas pustaka diunggah ke Files API saat pertama dibutuhkan sebuah sesi, dan `file_id`-nya disimpan di `generation_sessions.options.remote_files`. Karena itu kolom `anthropic_file_id` di `library_files` tidak dipakai.
- Nama berkas di container = nama asli unggahan (dibersihkan). Nama kembar diberi akhiran peran. Nama yang sama dipakai untuk variabel prompt `{{file_*}}`.
- Setelah seluruh task sesi selesai, salinan di Files API dihapus bila pengaturan "hapus berkas" aktif. Bila dibutuhkan lagi (misalnya generate ulang pada container baru), berkas diunggah ulang otomatis.

## D-011 · Ekstraksi teks DOCX untuk pemeriksaan

Pemeriksaan lapis 2 membaca teks langsung dari XML DOCX (badan, tabel, header, footer, kotak teks) lewat `DocxText`. Cara ini lebih toleran dibanding pembaca PhpWord, yang kerap gagal pada dokumen dengan elemen yang tidak didukung. PhpWord tetap dipakai untuk membuat DOCX uji.

## D-012 · Mesin prompt

- Prompt default per modul berasal dari berkas yang dikembalikan `defaultPrompts()` (Modul 1: `resources/prompts/rps_kontrak.md`, `resources/prompts/silabus.md`). Saat admin menyunting, isi berkas disimpan sebagai versi 1, lalu setiap suntingan menjadi versi baru. Admin dapat mengaktifkan kembali versi mana pun.
- Pengguna dapat menyunting prompt untuk sesinya (`generation_sessions.prompts`), menyimpannya sebagai prompt pribadi (dipakai otomatis pada sesi berikutnya), atau mengembalikannya ke default.
- Variabel `{{nama}}` diganti saat Job direncanakan. Variabel tak dikenal dibiarkan apa adanya dan ditampilkan sebagai peringatan. Variabel penting yang hilang (Modul 1: `{{nama_mk}}`, `{{kode_mk}}`, `{{data_mk}}`, `{{daftar_mk}}`) juga memunculkan peringatan.
- Variabel tambahan di luar spesifikasi: `{{nama_kaprodi}}` dan `{{nama_dekan}}`.

## D-013 · Instruksi sistem tetap Modul 1

Ditulis di `app/Modules/PerangkatPembelajaran/SystemInstructions.php` (tidak dapat diedit dari UI) dan dikirim sebagai `system` pada setiap Job. Isinya:

1. **Peran berkas** (dengan nama berkas sebenarnya): kurikulum = sumber data; contoh RPS = acuan gaya, kelengkapan, dan kedalaman saja (materi, Sub-CPMK, dan pustakanya tidak boleh disalin); template = format wajib yang diisi tanpa mengubah tata letak, kop, warna, ukuran kolom, maupun urutan bagian.
2. Kode dan rumusan CPL/CPMK, SKS, dan semester wajib sama persis dengan data MK terverifikasi; dilarang mencantumkan CPMK milik MK lain.
3. Struktur pertemuan menyesuaikan jenis MK dan template (praktikum, magang/PKL, dan tugas akhir tidak dipaksa ke pola 16 pertemuan); metode berpusat pada mahasiswa; total bobot 100%; pustaka usulan wajib nyata dan ditandai "(perlu verifikasi)".
4. Data identitas sesi dipakai persis bila diisi. Bila kosong, Claude diminta membiarkannya kosong/bertitik-titik dan tidak mengarangnya; demikian pula nama dosen, NIP, nomor dokumen, dan tanda tangan.
5. Kontrak Perkuliahan wajib memakai komponen/bobot penilaian, jadwal, dan pustaka yang sama dengan RPS dalam percakapan yang sama.
6. Berkas final disimpan ke `$OUTPUT_DIR` dengan nama yang ditentukan aplikasi, lalu diperiksa ulang dari penanda template yang tersisa. Setiap jawaban diakhiri satu blok ```json laporan pemeriksaan sesuai skema.

Prompt pemilik dikirim utuh. Aplikasi hanya menambahkan catatan tahap di bawahnya (misalnya "Tahap 1 dari 2: susun RPS saja…") agar RPS dan Kontrak dihasilkan berurutan di container yang sama.

## D-014 · Laporan RPS ditambah `komponen_penilaian`

Spesifikasi meminta pemeriksaan "komponen dan bobot penilaian di laporan Kontrak sama dengan di laporan RPS", tetapi skema laporan RPS tidak memuat daftar komponen. Karena itu laporan RPS ditambah kolom `komponen_penilaian` (`[{nama, bobot}]`) agar perbandingan dapat dilakukan. Nama komponen dibandingkan setelah dinormalisasi (huruf kecil, tanpa tanda baca).

## D-015 · Silabus Prodi bertahap

- Satu Job tingkat sesi (tanpa item). Langkahnya dibagi per semester; semester berisi lebih dari 10 MK dipecah menjadi beberapa bagian. Semua langkah berjalan dalam container dan percakapan yang sama.
- Langkah pertama memuat prompt Silabus pemilik (dengan `{{daftar_mk}}` berisi seluruh MK). Setiap langkah menyertakan data lengkap MK kelompoknya.
- Dokumen kerja disimpan di `/tmp/kerja/` dalam container. Hanya langkah terakhir yang menyimpan berkas final ke `$OUTPUT_DIR` dan mengembalikan laporan, sehingga hanya satu dokumen yang tersimpan.
- Silabus memuat **seluruh** MK terverifikasi, tidak hanya MK yang dicentang untuk RPS.

## D-016 · Pemeriksaan mandiri Modul 1 (lapis 2)

- Kode diperiksa dengan pencocokan toleran pemisah ("CPMK-019.1" ≈ "CPMK0191"). Sub-CPMK seperti "CPMK0191.1" dianggap menyebut CPMK0191.
- "CPMK milik MK lain" hanya dihitung untuk kode yang unik bagi satu MK lain dan tidak dimiliki MK ini. Dengan begitu, penomoran CPMK yang sama di banyak MK (mis. CPMK1) tidak memicu peringatan palsu.
- SKS dianggap tercantum bila pola seperti "3 SKS", "SKS: 3", atau "Bobot … 3" ditemukan.
- Penanda template yang tersisa: "[isi …]", "<isi …>", "{{…}}", "lorem ipsum", "XXX", serta baris bertitik-titik/bergaris bawah panjang, kecuali pada baris identitas (nama, dosen, NIP/NIDN, tanda tangan, ketua, dekan, tanggal, nomor, dsb.).
- Laporan Claude juga diperiksa silang: kode MK, SKS, `cpmk_digunakan`, total bobot 100%, `bobot_konsisten`, `cpmk_tanpa_subcpmk`, `konsisten_dengan_rps`, `mk_terlewat`, dan `jumlah_mk_di_dokumen`.

## D-017 · Penyesuaian terhadap template resmi (docs/referensi)

Berdasarkan Template RPS, Template Kontrak Perkuliahan, dan Template Silabus Edisi 1 Tahun 2025, serta contoh RPS (PDF):

- **Ekstraksi teks:** satu baris tabel DOCX = satu baris teks (sel dipisah tab), sehingga label dan isinya tetap bersama (mis. "Dosen Pengampu : ……").
- **Penanda isian yang dikenali** (selain titik-titik): `[tuliskan …]`, `[kode mata kuliah]`, `[nama program studi]`, `(Petunjuk: …)`, "Petunjuk pengisian", "Tulis materi/pustaka …", "… mampu….", dan sel tabel yang hanya berisi "….".
- **Boleh tetap kosong:** baris kop (Fakultas/Program Studi), kontrol dokumen (Kode, Edisi, Tanggal, Revisi), otorisasi, dosen, hari/waktu/kelas, dan tempat tanda tangan "(………)". "Mata Kuliah ……" dan "tahun akademik ……" tetap ditandai karena datanya selalu tersedia.
- **SKS** juga dikenali dalam format template `T=, P=3, L=, S=`. Lolos bila jumlahnya sama dengan total SKS dan rinciannya sesuai data MK.
- **Skill `pdf`** ikut dimuat bila salah satu berkas masukan berformat PDF (mis. contoh RPS).
- **Instruksi sistem ditambah aturan pengisian template:** ganti semua petunjuk/penanda; ganti baris contoh bawaan template (mis. contoh Sub-CPMK, minggu, rubrik); pertahankan catatan baku ("Keterangan", "*)"); isi `T=, P=, L=, S=`; tambahkan baris tabel dengan format yang sama. Untuk Silabus: sampul dan halaman pengesahan sekali di awal, lalu blok isian per MK digandakan untuk setiap MK.
- **Dokumen kurikulum berbeda di setiap prodi.** Aplikasi tidak bergantung pada tata letak dokumen kurikulum tertentu: Claude mengekstrak ke skema JSON yang sama, lalu prodi memverifikasinya di tabel.
