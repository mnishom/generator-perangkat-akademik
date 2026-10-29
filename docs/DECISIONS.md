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
