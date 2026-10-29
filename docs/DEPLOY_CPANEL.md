# Panduan Deploy ke Shared Hosting cPanel

Panduan ini untuk server cPanel dengan PHP ≥ 8.2, MySQL/MariaDB, Terminal cPanel (`proc_open` aktif), dan **tanpa Node.js**.

## 0. Prasyarat server

- **PHP 8.2+** dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `zip`, `xml`, `dom`, `fileinfo`, `intl`, `gd`. Atur di *Select PHP Version* / *MultiPHP INI Editor*.
- Batas unggah PHP (MultiPHP INI Editor) minimal sebesar batas unggahan di **Admin → Pengaturan** (default 20 MB):
  - `upload_max_filesize = 25M`
  - `post_max_size = 30M`
  - `memory_limit = 256M`
- Koneksi keluar ke `https://api.anthropic.com` (sudah terverifikasi).
- Google Workspace: buat OAuth Client (Web application) di Google Cloud Console dengan *Authorized redirect URI*: `https://DOMAIN-ANDA/auth/google/callback`. Atur *OAuth consent screen* sebagai **Internal** agar hanya akun Workspace kampus yang bisa masuk (aplikasi tetap memeriksa domain di sisi server).

## 1. Build aset di komputer lokal

Server tidak memiliki Node.js, jadi aset dibuild di lokal:

```bash
npm ci
npm run build
```

Folder `public/build` ikut di-commit (lihat `docs/DECISIONS.md` D-005). Bila mengunggah manual, pastikan folder `public/build` ikut terunggah.

## 2. Unggah kode

Pilihan A, lewat Git (*cPanel → Git Version Control*) atau Terminal:

```bash
cd ~
git clone https://github.com/mnishom/generator-perangkat-akademik.git app
```

Pilihan B: unggah arsip ZIP repo ke `~/app` lalu ekstrak.

**Letakkan aplikasi di luar `public_html`** (misalnya `~/app`). Hanya folder `public` yang boleh diakses web:

- **Domain/subdomain baru:** atur *Document Root* ke `/home/USER/app/public`.
- **Domain utama (`public_html`) tidak bisa diubah:** ganti `public_html` dengan symlink ke `app/public`:

  ```bash
  mv ~/public_html ~/public_html.bak && ln -s ~/app/public ~/public_html
  ```

## 3. Composer (di Terminal cPanel)

```bash
cd ~/app
composer install --no-dev --optimize-autoloader
```

Bila perintah `composer` tidak tersedia, unduh dulu `composer.phar`, lalu jalankan `php composer.phar install --no-dev --optimize-autoloader`.

## 4. Berkas `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Lalu sunting `.env` (*File Manager → Edit*):

```dotenv
APP_NAME="Generator Perangkat Akademik"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMAIN-ANDA
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=user_dbname
DB_USERNAME=user_dbuser
DB_PASSWORD=...

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=1500

GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
ALLOWED_EMAIL_DOMAIN=harkatnegeri.ac.id
ADMIN_EMAILS=email.admin@harkatnegeri.ac.id

ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-sonnet-5-5
DEV_LOGIN=false
```

Catatan:

- `ANTHROPIC_API_KEY` **hanya** disimpan di `.env`. Berkas ini tidak pernah di-commit dan tidak dapat diakses web (berada di luar `public`).
- `ADMIN_EMAILS` menjadikan akun tersebut admin saat login pertama.
- `DEV_LOGIN` wajib `false` di produksi.

Database dibuat lewat *cPanel → MySQL® Databases*: buat database dan user, lalu beri user tersebut **ALL PRIVILEGES**.

## 5. Migrasi, storage, dan optimasi

```bash
php artisan migrate --force
php artisan db:seed --force    # opsional: contoh fakultas/prodi
php artisan storage:link       # tidak wajib: semua berkas disimpan privat dan diunduh lewat controller
php artisan optimize
```

Izin folder:

```bash
chmod -R 775 storage bootstrap/cache
```

Berkas unggahan dan hasil generate tersimpan di `storage/app/private` (disk privat, tidak dapat diakses langsung lewat URL).

## 6. Cron (wajib, untuk antrean generate)

Semua pemanggilan Claude berjalan sebagai Job antrean. Tambahkan di *cPanel → Cron Jobs*, dengan jadwal **setiap menit** (`* * * * *`):

```
cd /home/USER/app && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Sesuaikan path PHP (cek dengan `which php` di Terminal; di cPanel sering `/usr/local/bin/php` atau `/opt/cpanel/ea-php83/root/usr/bin/php`).

Jadwal ini menjalankan worker `queue:work --stop-when-empty --max-time=50` setiap menit di latar belakang (lihat `routes/console.php`). Jumlah Job generate yang berjalan bersamaan dibatasi di **Admin → Pengaturan** (default 2).

Bila hosting mematikan proses yang berjalan lama, Job **dilanjutkan otomatis** dari langkah terakhir yang selesai pada menit berikutnya, setelah `DB_QUEUE_RETRY_AFTER` terlewati.

## 7. Uji setelah deploy

1. Buka situs, lalu login dengan akun `@harkatnegeri.ac.id` yang tercantum di `ADMIN_EMAILS`.
2. Di **Admin → Prodi & Fakultas**, isi fakultas dan prodi (termasuk nama Dekan/Kaprodi).
3. Di **Admin → Pengaturan**, periksa model, batas unggahan, dan batas Job bersamaan.
4. Uji integrasi Claude (memakai sedikit kuota API):

   ```bash
   php artisan claude:smoke-test
   ```

5. Di **Pustaka Berkas**, unggah template resmi sebagai **berkas bersama** (khusus admin).

## 8. Pembaruan aplikasi

```bash
cd ~/app
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
php artisan up
```

Jangan lupa: aset frontend dibuild di lokal (`npm run build`) dan `public/build` di-commit sebelum `git pull` di server.

## 9. Pemecahan masalah

| Gejala | Penyebab umum |
|---|---|
| Proses generate tidak pernah mulai | Cron belum dipasang atau path PHP salah. Cek tabel `jobs` dan jalankan `php artisan schedule:run` manual. |
| Job berulang atau dobel | `DB_QUEUE_RETRY_AFTER` lebih kecil dari durasi Job. Harus ≥ 1500. |
| Unggahan gagal untuk berkas besar | `upload_max_filesize`/`post_max_size` PHP lebih kecil dari batas di Pengaturan. |
| "Hanya akun Google @… yang dapat masuk" | Akun bukan akun Workspace kampus, atau `ALLOWED_EMAIL_DOMAIN` salah. |
| Galat 429/529 dari Claude | Batas laju API. Job dicoba ulang otomatis; turunkan "Job berjalan bersamaan". |
| Halaman error 500 | Lihat `storage/logs/laravel.log`. API key tidak pernah ditulis ke log. |
