<?php

namespace App\Livewire;

use App\Models\Prodi;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Pilih Program Studi')]
class PilihProdi extends Component
{
    #[Validate('required|integer|exists:prodis,id')]
    public ?int $prodiId = null;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->prodi_locked && $user->prodi_id) {
            session()->flash('warning', 'Program Studi Anda dikunci oleh admin.');
            $this->redirectRoute('dashboard');

            return;
        }

        $this->prodiId = $user->prodi_id;
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_if($user->prodi_locked && $user->prodi_id, 403, 'Program Studi Anda dikunci oleh admin.');

        $this->validate();
        abort_unless(Prodi::active()->whereKey($this->prodiId)->exists(), 422);

        $user->prodi_id = $this->prodiId;
        $user->save();

        session()->flash('success', 'Program Studi tersimpan.');
        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.pilih-prodi', [
            'prodis' => Prodi::active()->with('fakultas')->orderBy('nama')->get()->groupBy(fn ($p) => $p->fakultas?->nama ?? 'Lainnya'),
        ]);
    }
}
