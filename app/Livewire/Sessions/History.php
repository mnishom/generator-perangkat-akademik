<?php

namespace App\Livewire\Sessions;

use App\Core\Modules\ModuleRegistry;
use App\Models\GenerationSession;
use App\Models\Prodi;
use App\Services\Audit;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Riwayat')]
class History extends Component
{
    use WithPagination;

    #[Url]
    public string $module = '';

    #[Url]
    public string $prodi = '';

    #[Url]
    public string $tahun = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    /** Admin: tampilkan riwayat seluruh pengguna (hanya baca, tercatat di audit log). */
    #[Url]
    public bool $all = false;

    public function updating($name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }

    public function updatedAll(bool $value): void
    {
        if ($value && auth()->user()->isAdmin()) {
            Audit::log('history.view_all');
        }
    }

    public function render(ModuleRegistry $registry)
    {
        $user = auth()->user();
        $all = $this->all && $user->isAdmin();

        $sessions = GenerationSession::query()
            ->with(['user', 'prodi'])
            ->withCount(['items as selected_count' => fn ($q) => $q->where('selected', true)])
            ->withCount('documents')
            ->withCount(['documents as attention_count' => fn ($q) => $q->where('status', 'perlu_perhatian')])
            ->when(! $all, fn ($q) => $q->where('user_id', $user->id))
            ->when($this->module, fn ($q) => $q->where('module_key', $this->module))
            ->when($this->prodi, fn ($q) => $q->where('prodi_id', $this->prodi))
            ->when($this->tahun, fn ($q) => $q->where('tahun_akademik', $this->tahun))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->latest()
            ->paginate(15);

        $base = GenerationSession::query()->when(! $all, fn ($q) => $q->where('user_id', $user->id));

        return view('livewire.sessions.history', [
            'sessions' => $sessions,
            'modules' => $registry->all(),
            'prodis' => $all ? Prodi::orderBy('nama')->get() : Prodi::whereIn('id', (clone $base)->distinct()->pluck('prodi_id'))->get(),
            'years' => (clone $base)->whereNotNull('tahun_akademik')->distinct()->orderByDesc('tahun_akademik')->pluck('tahun_akademik'),
            'isAdmin' => $user->isAdmin(),
            'showAll' => $all,
        ]);
    }
}
