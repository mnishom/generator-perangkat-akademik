<?php

namespace App\Modules\PerangkatPembelajaran\Steps;

use App\Livewire\Wizard\InputFilesStep;

class ContohRpsStep extends InputFilesStep
{
    protected function roles(): array
    {
        return ['contoh_rps'];
    }

    protected function title(): string
    {
        return 'Contoh RPS final';
    }

    protected function intro(): string
    {
        return 'RPS yang sudah lengkap dan disetujui. Dipakai Claude sebagai acuan gaya, kelengkapan, dan kedalaman isi saja; isinya tidak disalin ke MK lain.';
    }
}
