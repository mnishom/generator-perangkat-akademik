<?php

namespace App\Modules\PerangkatPembelajaran\Steps;

use App\Core\Generation\SessionLauncher;
use App\Core\Modules\ModuleAccess;
use App\Core\Wizard\WizardStep;
use App\Models\GeneratedDocument;
use App\Models\GenerationTask;
use App\Services\Settings;
use RuntimeException;

/**
 * Generate massal: satu Job per MK (RPS → Kontrak) + satu Job Silabus per sesi.
 */
class GenerateStep extends WizardStep
{
    /** MK yang tidak perlu di-generate lagi: uji coba yang selesai dengan dokumen lolos. */
    private function doneItemIds(): array
    {
        $session = $this->session();

        return GenerationTask::where('generation_session_id', $session->id)
            ->where('status', GenerationTask::STATUS_DONE)
            ->whereNotNull('generation_item_id')
            ->whereDoesntHave('documents', fn ($q) => $q->where('status', GeneratedDocument::STATUS_FAILED))
            ->pluck('generation_item_id')->unique()->all();
    }

    public function start(SessionLauncher $launcher): void
    {
        $session = $this->session();
        $ids = $session->items()->where('selected', true)->whereNotIn('id', $this->doneItemIds())->pluck('id')->all();
        $silabus = (bool) $session->option('generate_silabus') && ! $session->tasks()->where('key', 'silabus')->exists();

        try {
            $tasks = $launcher->launch($session, $ids, includeSessionLevel: $silabus);
        } catch (RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $session->options = array_merge($session->options ?? [], ['wizard_done' => true]);
        $session->save();

        session()->flash('success', count($tasks).' proses generate masuk antrean. Anda boleh meninggalkan halaman ini; progres tampil langsung.');
        $this->redirectRoute('sessions.show', $session, navigate: true);
    }

    public function back(): void
    {
        $this->dispatch('wizard-back');
    }

    public function render(ModuleAccess $access)
    {
        $session = $this->session();
        $selected = $session->items()->where('selected', true)->get();
        $done = $this->doneItemIds();
        $inputs = $session->inputs ?? [];

        return view('modules.perangkat-pembelajaran.generate-step', [
            'session' => $session,
            'pending' => $selected->whereNotIn('id', $done),
            'skipped' => $selected->whereIn('id', $done),
            'hasRps' => ! empty($inputs['template_rps']),
            'hasKontrak' => ! empty($inputs['template_kontrak']),
            'silabus' => (bool) $session->option('generate_silabus'),
            'allCount' => $session->items()->count(),
            'model' => $session->model ?: $access->modelFor($session->module_key),
            'concurrency' => app(Settings::class)->get('max_concurrent_jobs'),
        ]);
    }
}
