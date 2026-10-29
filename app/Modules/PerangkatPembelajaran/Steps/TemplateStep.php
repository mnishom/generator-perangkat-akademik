<?php

namespace App\Modules\PerangkatPembelajaran\Steps;

use App\Livewire\Wizard\InputFilesStep;

class TemplateStep extends InputFilesStep
{
    protected function roles(): array
    {
        return ['template_rps', 'template_kontrak', 'template_silabus'];
    }

    protected function title(): string
    {
        return 'Template dokumen';
    }

    protected function intro(): string
    {
        return 'Ketiga template opsional. Jenis dokumen yang dapat di-generate mengikuti template yang tersedia: Template RPS → RPS per MK; + Template Kontrak → Kontrak Perkuliahan (diturunkan dari RPS); Template Silabus → satu Silabus Prodi. Template bersama dari admin dapat dipilih dari pustaka.';
    }

    public function continue(): void
    {
        $this->resetErrorBag();
        $inputs = $this->session()->inputs ?? [];
        if (empty($inputs['template_rps']) && empty($inputs['template_silabus'])) {
            $this->addError('uploads.template_rps', 'Pilih minimal Template RPS atau Template Silabus.');

            return;
        }
        if (! empty($inputs['template_kontrak']) && empty($inputs['template_rps'])) {
            $this->addError('uploads.template_kontrak', 'Kontrak Perkuliahan diturunkan dari RPS, jadi membutuhkan Template RPS juga.');

            return;
        }
        parent::continue();
    }
}
