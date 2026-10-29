<?php

use Illuminate\Support\Facades\Schedule;

/*
| Cron cPanel menjalankan `php artisan schedule:run` setiap menit (lihat docs/DEPLOY_CPANEL.md).
|
| Setiap menit satu worker antrean dijalankan di latar belakang. Worker berhenti saat
| antrean kosong atau setelah 50 detik (dicek di antara Job), sehingga tidak bentrok
| dengan batas waktu proses shared hosting. Job yang sedang berjalan tetap diselesaikan;
| jumlah Job generate yang berjalan bersamaan dibatasi oleh aplikasi (Pengaturan).
*/
Schedule::command('queue:work database --stop-when-empty --max-time=50 --timeout=1200 --sleep=3 --tries=0')
    ->everyMinute()
    ->runInBackground()
    ->name('queue-worker');

Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('queue:prune-batches')->daily();
