<?php

use App\Modules\PerangkatPembelajaran\PerangkatPembelajaranModule;

/*
| Modul aktif. Tambahkan modul baru dengan membuat folder app/Modules/<Nama>/,
| kelas yang mengimplementasikan App\Core\Modules\ModuleContract, lalu
| mendaftarkannya di sini. Kode inti tidak perlu diubah.
*/

return [
    'modules' => [
        PerangkatPembelajaranModule::class,
    ],
];
