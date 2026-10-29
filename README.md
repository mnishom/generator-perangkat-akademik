# Generator Perangkat Akademik

Platform modular bagi Program Studi di Universitas Harkat Negeri untuk menyusun perangkat akademik (RPS, Kontrak Perkuliahan, Silabus, dan modul lain kelak) dengan bantuan Claude API.

- Stack: Laravel 12 · Livewire 3 · Tailwind CSS 4 · MySQL/MariaDB · Pest
- Keputusan teknis: [`docs/DECISIONS.md`](docs/DECISIONS.md)
- Panduan deploy cPanel: [`docs/DEPLOY_CPANEL.md`](docs/DEPLOY_CPANEL.md)
- Modul aktif: `config/modules.php` (Modul 1: Perangkat Pembelajaran)

## Pengembangan lokal

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Untuk login tanpa Google di lokal: set `DEV_LOGIN=true`, lalu buka `/dev/login/{id}`.

## Test

```bash
./vendor/bin/pest
```

Test tidak pernah memanggil Claude API sungguhan.
