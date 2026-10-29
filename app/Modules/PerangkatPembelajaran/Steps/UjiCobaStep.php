<?php

namespace App\Modules\PerangkatPembelajaran\Steps;

use App\Core\Generation\SessionLauncher;
use App\Core\Wizard\WizardStep;
use App\Models\GenerationTask;
use RuntimeException;

/**
 * Uji coba satu MK sebelum generate massal (disarankan, dapat dilewati).
 */
class UjiCobaStep extends WizardStep
{
    public ?int $itemId = null;

    public function mount(): void
    {
        $session = $this->session();
        $this->itemId = $session->option('trial_item_id') ?? $session->items()->where('selected', true)->value('id');
    }

    public function run(SessionLauncher $launcher): void
    {
        $session = $this->session();
        $item = $session->items()->where('selected', true)->findOrFail($this->itemId);

        try {
            $tasks = $launcher->launch($session, [$item->id], includeSessionLevel: false);
        } catch (RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $session->options = array_merge($session->options ?? [], ['trial_item_id' => $item->id, 'trial_task_id' => $tasks[0]->id ?? null]);
        $session->save();
        $this->toast('Uji coba dimulai. Hasil muncul di bawah setelah selesai.', 'info');
    }

    public function editPrompt(): void
    {
        $this->dispatch('wizard-back');
    }

    public function skip(): void
    {
        $this->next();
    }

    public function render()
    {
        $session = $this->session();
        $task = $session->option('trial_task_id') ? GenerationTask::with('documents')->find($session->option('trial_task_id')) : null;
        $docTypes = $this->module()->documentTypes();

        return view('modules.perangkat-pembelajaran.uji-coba-step', [
            'items' => $session->items()->where('selected', true)->get(),
            'task' => $task,
            'docs' => $task ? $task->documents()->orderByDesc('version')->get()->unique('doc_type') : collect(),
            'docTypes' => $docTypes,
            'hasRps' => ! empty($session->inputs['template_rps']),
        ]);
    }
}
