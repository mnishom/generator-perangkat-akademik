<?php

namespace App\Livewire\Library;

use App\Core\Library\DocumentLibrary;
use App\Core\Modules\ModuleRegistry;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Pustaka Dokumen')]
class DocumentLibraryPage extends Component
{
    #[Url]
    public string $module = '';

    #[Url]
    public string $q = '';

    public function render(DocumentLibrary $library, ModuleRegistry $registry)
    {
        $docs = $library->finals(auth()->user(), $this->module ?: null)
            ->when($this->q, fn ($c) => $c->filter(fn ($d) => str_contains(mb_strtolower($d->filename.' '.$d->ref_code.' '.($d->item?->ref_label ?? '')), mb_strtolower($this->q))));

        $modules = $registry->all();

        return view('livewire.library.document-library-page', [
            'modules' => $modules,
            'grouped' => $docs->groupBy('module_key')->map(fn ($c) => $c->groupBy('doc_type')),
            'typeLabel' => fn (string $m, string $t) => isset($modules[$m]) ? ($modules[$m]->documentTypes()[$t]->label ?? $t) : $t,
        ]);
    }
}
