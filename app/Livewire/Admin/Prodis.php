<?php

namespace App\Livewire\Admin;

use App\Models\Fakultas;
use App\Models\Prodi;
use App\Services\Audit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Prodi & Fakultas')]
class Prodis extends Component
{
    public ?int $fakultasId = null;

    public array $fakultasForm = ['nama' => '', 'singkatan' => '', 'nama_dekan' => ''];

    public ?int $prodiId = null;

    public array $prodiForm = ['fakultas_id' => null, 'kode' => '', 'jenjang' => '', 'nama' => '', 'nama_kaprodi' => '', 'is_active' => true];

    public bool $showFakultas = false;

    public bool $showProdi = false;

    public function editFakultas(?int $id = null): void
    {
        $f = $id ? Fakultas::findOrFail($id) : new Fakultas;
        $this->fakultasId = $f->id;
        $this->fakultasForm = ['nama' => $f->nama ?? '', 'singkatan' => $f->singkatan ?? '', 'nama_dekan' => $f->nama_dekan ?? ''];
        $this->showFakultas = true;
        $this->resetValidation();
    }

    public function saveFakultas(): void
    {
        $data = $this->validate([
            'fakultasForm.nama' => ['required', 'string', 'max:255'],
            'fakultasForm.singkatan' => ['nullable', 'string', 'max:32'],
            'fakultasForm.nama_dekan' => ['nullable', 'string', 'max:255'],
        ], attributes: ['fakultasForm.nama' => 'nama fakultas'])['fakultasForm'];

        $f = Fakultas::updateOrCreate(['id' => $this->fakultasId], array_map(fn ($v) => $v === '' ? null : $v, $data));
        Audit::log($this->fakultasId ? 'fakultas.update' : 'fakultas.create', $f, $data);

        $this->showFakultas = false;
        $this->dispatch('toast', type: 'success', message: 'Fakultas tersimpan.');
    }

    public function editProdi(?int $id = null): void
    {
        $p = $id ? Prodi::findOrFail($id) : new Prodi(['is_active' => true]);
        $this->prodiId = $p->id;
        $this->prodiForm = [
            'fakultas_id' => $p->fakultas_id,
            'kode' => $p->kode ?? '',
            'jenjang' => $p->jenjang ?? '',
            'nama' => $p->nama ?? '',
            'nama_kaprodi' => $p->nama_kaprodi ?? '',
            'is_active' => (bool) $p->is_active,
        ];
        $this->showProdi = true;
        $this->resetValidation();
    }

    public function saveProdi(): void
    {
        $data = $this->validate([
            'prodiForm.fakultas_id' => ['nullable', 'integer', 'exists:fakultas,id'],
            'prodiForm.kode' => ['nullable', 'string', 'max:32', Rule::unique('prodis', 'kode')->ignore($this->prodiId)],
            'prodiForm.jenjang' => ['nullable', 'string', 'max:16'],
            'prodiForm.nama' => ['required', 'string', 'max:255'],
            'prodiForm.nama_kaprodi' => ['nullable', 'string', 'max:255'],
            'prodiForm.is_active' => ['boolean'],
        ], attributes: ['prodiForm.nama' => 'nama prodi', 'prodiForm.kode' => 'kode prodi'])['prodiForm'];

        $p = Prodi::updateOrCreate(['id' => $this->prodiId], array_map(fn ($v) => $v === '' ? null : $v, $data));
        Audit::log($this->prodiId ? 'prodi.update' : 'prodi.create', $p, $data);

        $this->showProdi = false;
        $this->dispatch('toast', type: 'success', message: 'Program Studi tersimpan.');
    }

    public function render()
    {
        return view('livewire.admin.prodis', [
            'fakultas' => Fakultas::withCount('prodis')->orderBy('nama')->get(),
            'prodis' => Prodi::with('fakultas')->withCount('users')->orderBy('nama')->get(),
        ]);
    }
}
