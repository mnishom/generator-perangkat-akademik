<?php

namespace App\Livewire\Modules;

use App\Core\Modules\ModuleAccess;
use App\Core\Modules\ModuleRegistry;
use App\Models\GenerationSession;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ModuleHome extends Component
{
    #[Locked]
    public string $module;

    public function mount(string $module): void
    {
        $this->module = $module;
    }

    public function start(): void
    {
        $user = auth()->user();
        $module = app(ModuleRegistry::class)->get($this->module);

        $session = GenerationSession::create([
            'module_key' => $module->key(),
            'user_id' => $user->id,
            'prodi_id' => $user->prodi_id,
            'tanggal_penyusunan' => now()->toDateString(),
            'status' => GenerationSession::STATUS_DRAFT,
        ]);

        $this->redirectRoute('modules.wizard', ['module' => $module->key(), 'session' => $session->id], navigate: true);
    }

    public function render(ModuleAccess $access)
    {
        $module = app(ModuleRegistry::class)->get($this->module);

        return view('livewire.modules.module-home', [
            'moduleDef' => $module,
            'generateOpen' => $access->generateOpen($module->key()),
            'canGenerate' => auth()->user()->canGenerate(),
            'sessions' => GenerationSession::ownedBy(auth()->user())
                ->where('module_key', $module->key())
                ->withCount('items')
                ->latest()
                ->limit(10)
                ->get(),
        ])->title($module->name());
    }
}
