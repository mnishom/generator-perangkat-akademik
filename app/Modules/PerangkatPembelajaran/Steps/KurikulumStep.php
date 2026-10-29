<?php

namespace App\Modules\PerangkatPembelajaran\Steps;

use App\Livewire\Wizard\InputFilesStep;

class KurikulumStep extends InputFilesStep
{
    protected function roles(): array
    {
        return ['kurikulum'];
    }

    protected function title(): string
    {
        return 'Dokumen kurikulum OBE prodi';
    }

    protected function intro(): string
    {
        return 'Unggah dokumen kurikulum yang memuat seluruh MK beserta peta keterkaitannya dengan CPL, CPMK, bahan kajian, SKS, dan semester. Nama berkas bebas.';
    }

    protected function changed(string $role): void
    {
        // Dokumen kurikulum berganti → data terverifikasi sebelumnya tidak berlaku untuk sesi ini.
        $session = $this->session();
        $session->update(['curriculum_extraction_id' => null]);
        $session->items()->doesntHave('documents')->delete();
    }
}
