<?php

namespace App\Livewire\Admin;

use App\Core\Modules\ModuleRegistry;
use App\Core\Prompts\PromptEngine;
use App\Services\Audit;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Prompt Default')]
class Prompts extends Component
{
    #[Url]
    public string $module = '';

    #[Url]
    public string $key = '';

    public string $body = '';

    public string $note = '';

    public function mount(ModuleRegistry $registry): void
    {
        $this->module = $registry->has($this->module) ? $this->module : (array_key_first($registry->all()) ?? '');
        $this->selectFirstKey();
        $this->load();
    }

    private function selectFirstKey(): void
    {
        $prompts = $this->module ? app(ModuleRegistry::class)->get($this->module)->defaultPrompts() : [];
        if (! array_key_exists($this->key, $prompts)) {
            $this->key = array_key_first($prompts) ?? '';
        }
    }

    public function updatedModule(): void
    {
        $this->selectFirstKey();
        $this->load();
    }

    public function updatedKey(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $this->body = $this->module && $this->key
            ? app(PromptEngine::class)->defaultBody(app(ModuleRegistry::class)->get($this->module), $this->key)
            : '';
        $this->note = '';
    }

    public function save(PromptEngine $engine, ModuleRegistry $registry): void
    {
        $this->validate(['body' => ['required', 'string', 'max:20000'], 'note' => ['nullable', 'string', 'max:255']]);
        $p = $engine->saveDefault($registry->get($this->module), $this->key, $this->body, auth()->user(), $this->note ?: null);
        Audit::log('prompt.save', $p, ['module' => $this->module, 'key' => $this->key, 'version' => $p->version]);
        $this->note = '';
        $this->dispatch('toast', type: 'success', message: "Prompt default disimpan sebagai versi {$p->version}.");
    }

    public function activate(int $version, PromptEngine $engine, ModuleRegistry $registry): void
    {
        $p = $engine->activateVersion($registry->get($this->module), $this->key, $version);
        Audit::log('prompt.rollback', $p, ['module' => $this->module, 'key' => $this->key, 'version' => $version]);
        $this->load();
        $this->dispatch('toast', type: 'success', message: "Versi {$version} kini aktif.");
    }

    public function render(ModuleRegistry $registry, PromptEngine $engine)
    {
        $module = $this->module ? $registry->get($this->module) : null;
        $defs = $module ? $engine->definitions($module) : [];

        return view('livewire.admin.prompts', [
            'modules' => $registry->all(),
            'prompts' => $module ? array_keys($module->defaultPrompts()) : [],
            'defs' => $defs,
            'history' => $module && $this->key ? $engine->history($module, $this->key) : collect(),
            'analysis' => $engine->analyze($this->body, $defs[$this->key]['variables'] ?? []),
        ]);
    }
}
