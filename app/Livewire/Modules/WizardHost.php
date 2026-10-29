<?php

namespace App\Livewire\Modules;

use App\Core\Modules\ModuleRegistry;
use App\Models\GenerationSession;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Wadah wizard generik: menampilkan stepper dan komponen langkah milik modul.
 */
class WizardHost extends Component
{
    #[Locked]
    public string $module;

    #[Locked]
    public int $sessionId;

    public function mount(string $module, GenerationSession $session): void
    {
        abort_unless($session->module_key === $module, 404);
        Gate::authorize('update', $session);

        $this->module = $module;
        $this->sessionId = $session->id;

        if (! $session->wizard_step || ! array_key_exists($session->wizard_step, $this->steps())) {
            $session->update(['wizard_step' => array_key_first($this->steps())]);
        }
    }

    private function steps(): array
    {
        return app(ModuleRegistry::class)->get($this->module)->wizardSteps();
    }

    private function session(): GenerationSession
    {
        return GenerationSession::findOrFail($this->sessionId);
    }

    #[On('wizard-next')]
    public function next(): void
    {
        $keys = array_keys($this->steps());
        $session = $this->session();
        $i = array_search($session->wizard_step, $keys, true);
        if ($i !== false && isset($keys[$i + 1])) {
            $session->update(['wizard_step' => $keys[$i + 1], 'options' => array_merge($session->options ?? [], ['wizard_max' => max($i + 1, (int) $session->option('wizard_max', 0))])]);
        }
    }

    #[On('wizard-back')]
    public function back(): void
    {
        $keys = array_keys($this->steps());
        $session = $this->session();
        $i = array_search($session->wizard_step, $keys, true);
        if ($i) {
            $session->update(['wizard_step' => $keys[$i - 1]]);
        }
    }

    /** Pindah ke langkah yang sudah pernah dicapai. */
    public function goTo(string $key): void
    {
        $keys = array_keys($this->steps());
        $session = $this->session();
        $target = array_search($key, $keys, true);
        if ($target !== false && $target <= (int) $session->option('wizard_max', 0)) {
            $session->update(['wizard_step' => $key]);
        }
    }

    public function render()
    {
        $session = $this->session();
        $module = app(ModuleRegistry::class)->get($this->module);
        $steps = $module->wizardSteps();

        return view('livewire.modules.wizard-host', [
            'session' => $session,
            'moduleDef' => $module,
            'steps' => $steps,
            'current' => $session->wizard_step,
            'currentIndex' => array_search($session->wizard_step, array_keys($steps), true),
            'maxIndex' => (int) $session->option('wizard_max', 0),
        ])->title($module->name());
    }
}
