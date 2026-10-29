<?php

namespace Database\Seeders;

use App\Models\Fakultas;
use App\Models\Prodi;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal minimal. Fakultas dan prodi sesungguhnya dikelola admin
     * melalui panel admin.
     */
    public function run(): void
    {
        $fakultas = Fakultas::firstOrCreate(['nama' => 'Fakultas Teknik dan Ilmu Komputer'], ['singkatan' => 'FTIK']);

        Prodi::firstOrCreate(
            ['kode' => 'DTI'],
            ['fakultas_id' => $fakultas->id, 'jenjang' => 'D4', 'nama' => 'Teknik Informatika']
        );
    }
}
