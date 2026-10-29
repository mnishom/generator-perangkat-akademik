<?php

namespace App\Core\Wizard;

use App\Core\Modules\ModuleContract;
use App\Core\Modules\ModuleRegistry;
use App\Models\GenerationSession;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Dasar komponen langkah wizard. Langkah bekerja langsung pada sesi draf di
 * database, lalu memanggil next() agar WizardHost maju ke langkah berikutnya.
 */
abstract class WizardStep extends Component
{
    #[Locked]
    public int $sessionId;

    protected function session(): GenerationSession
    {
        $session = GenerationSession::findOrFail($this->sessionId);
        Gate::authorize('update', $session);

        return $session;
    }

    protected function module(): ModuleContract
    {
        return app(ModuleRegistry::class)->get($this->session()->module_key);
    }

    protected function next(): void
    {
        $this->dispatch('wizard-next');
    }

    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }
}
