<?php

namespace App\Livewire\Wizard;

use App\Core\Prompts\PromptEngine;
use App\Core\Wizard\WizardStep;

/**
 * Langkah wizard generik: menyunting prompt modul untuk sesi ini, menyimpannya
 * sebagai prompt pribadi, atau mengembalikannya ke default.
 */
class PromptStep extends WizardStep
{
    public array $bodies = [];

    public function mount(): void
    {
        $session = $this->session();
        $engine = app(PromptEngine::class);
        foreach (array_keys($this->definitions()) as $key) {
            $this->bodies[$key] = $session->prompts[$key] ?? $engine->initialBody(auth()->user(), $this->module(), $key);
        }
    }

    private function definitions(): array
    {
        return app(PromptEngine::class)->definitions($this->module(), $this->session());
    }

    public function resetDefault(string $key): void
    {
        abort_unless(array_key_exists($key, $this->definitions()), 404);
        $this->bodies[$key] = app(PromptEngine::class)->defaultBody($this->module(), $key);
        $this->persist();
        $this->toast('Prompt dikembalikan ke default.', 'info');
    }

    public function savePersonal(string $key): void
    {
        abort_unless(array_key_exists($key, $this->definitions()), 404);
        $this->validate(["bodies.$key" => ['required', 'string', 'max:20000']]);
        app(PromptEngine::class)->savePersonal(auth()->user(), $this->module(), $key, $this->bodies[$key]);
        $this->persist();
        $this->toast('Disimpan sebagai prompt pribadi; akan dipakai otomatis pada sesi berikutnya.');
    }

    public function loadPersonal(string $key): void
    {
        $personal = app(PromptEngine::class)->personal(auth()->user(), $this->module(), $key);
        if ($personal) {
            $this->bodies[$key] = $personal->body;
            $this->persist();
        }
    }

    public function continue(): void
    {
        $this->validate(['bodies.*' => ['required', 'string', 'max:20000']], attributes: ['bodies.*' => 'prompt']);
        $this->persist();
        $this->next();
    }

    public function back(): void
    {
        $this->persist();
        $this->dispatch('wizard-back');
    }

    private function persist(): void
    {
        $session = $this->session();
        $session->prompts = array_merge($session->prompts ?? [], array_intersect_key($this->bodies, $this->definitions()));
        $session->save();
    }

    public function render()
    {
        $engine = app(PromptEngine::class);
        $defs = $this->definitions();
        $analysis = [];
        $isDefault = [];
        $hasPersonal = [];
        foreach ($defs as $key => $def) {
            $analysis[$key] = $engine->analyze($this->bodies[$key] ?? '', $def['variables'] ?? []);
            $isDefault[$key] = trim($this->bodies[$key] ?? '') === trim($engine->defaultBody($this->module(), $key));
            $hasPersonal[$key] = (bool) $engine->personal(auth()->user(), $this->module(), $key);
        }

        return view('livewire.wizard.prompt-step', compact('defs', 'analysis', 'isDefault', 'hasPersonal'));
    }
}
