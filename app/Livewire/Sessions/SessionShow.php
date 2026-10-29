<?php

namespace App\Livewire\Sessions;

use App\Core\Generation\SessionLauncher;
use App\Core\Modules\ModuleRegistry;
use App\Models\GenerationSession;
use App\Services\Audit;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Rincian sesi generate (generik untuk semua modul) dengan progres langsung.
 */
class SessionShow extends Component
{
    #[Locked]
    public int $sessionId;

    public function mount(GenerationSession $session): void
    {
        Gate::authorize('view', $session);
        $this->sessionId = $session->id;

        if ($session->user_id !== auth()->id()) {
            Audit::log('session.view', $session, ['owner_id' => $session->user_id]);
        }
    }

    public function cancel(SessionLauncher $launcher): void
    {
        $session = GenerationSession::findOrFail($this->sessionId);
        Gate::authorize('update', $session);
        $n = $launcher->cancel($session);
        $this->dispatch('toast', type: 'info', message: "{$n} proses antre dibatalkan.");
    }

    public function render()
    {
        $session = GenerationSession::with(['user', 'prodi', 'items', 'tasks', 'documents' => fn ($q) => $q->orderByDesc('version')])->findOrFail($this->sessionId);
        $module = app(ModuleRegistry::class)->has($session->module_key) ? app(ModuleRegistry::class)->get($session->module_key) : null;

        return view('livewire.sessions.session-show', [
            'session' => $session,
            'moduleDef' => $module,
            'docTypes' => $module?->documentTypes() ?? [],
            'readOnly' => $session->user_id !== auth()->id(),
        ])->title($session->title ?: 'Sesi #'.$session->id);
    }
}
