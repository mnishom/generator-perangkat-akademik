<?php

namespace App\Core\Generation;

use App\Core\Generation\Jobs\RunGenerationTask;
use App\Core\Modules\JobPlan;
use App\Core\Modules\JobStep;
use App\Core\Modules\ModuleAccess;
use App\Core\Modules\ModuleRegistry;
use App\Models\GenerationSession;
use App\Models\GenerationTask;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengubah sesi menjadi task-task antrean berdasarkan planJobs() modul.
 */
class SessionLauncher
{
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly ModuleAccess $access,
        private readonly GenerationGuard $guard,
    ) {}

    /**
     * @param  int[]|null  $itemIds  hanya butir ini (mis. uji coba satu MK); null = semua butir terpilih
     * @param  bool  $includeSessionLevel  sertakan Job tingkat sesi (mis. Silabus Prodi)
     * @return GenerationTask[]
     */
    public function launch(GenerationSession $session, ?array $itemIds = null, bool $includeSessionLevel = true): array
    {
        if ($reason = $this->guard->deny($session->user, $session->module_key)) {
            throw new RuntimeException($reason);
        }

        $module = $this->modules->get($session->module_key);
        $model = $session->model ?: $this->access->modelFor($module->key());

        $plans = array_filter($module->planJobs($session), function (JobPlan $plan) use ($itemIds, $includeSessionLevel) {
            if ($plan->item === null) {
                return $includeSessionLevel;
            }

            return $itemIds === null ? $plan->item->selected : in_array($plan->item->id, $itemIds, true);
        });

        $tasks = DB::transaction(function () use ($session, $plans, $model) {
            $tasks = [];
            foreach ($plans as $plan) {
                $active = $session->tasks()->where('key', $plan->key)
                    ->whereIn('status', [GenerationTask::STATUS_QUEUED, GenerationTask::STATUS_RUNNING, GenerationTask::STATUS_WAITING])
                    ->exists();
                if ($active) {
                    continue;
                }

                $tasks[] = $session->tasks()->create([
                    'generation_item_id' => $plan->item?->id,
                    'key' => $plan->key,
                    'label' => $plan->label,
                    'steps' => array_map(fn (JobStep $s) => $s->toArray(), $plan->steps),
                    'skills' => $plan->skills,
                    'model' => $model,
                    'status' => GenerationTask::STATUS_QUEUED,
                ]);
            }

            $session->forceFill([
                'model' => $model,
                'status' => GenerationSession::STATUS_QUEUED,
                'started_at' => $session->started_at ?? now(),
                'finished_at' => null,
            ])->save();

            return $tasks;
        });

        foreach ($tasks as $task) {
            RunGenerationTask::dispatch($task->id);
        }

        return $tasks;
    }

    /**
     * Tambahkan satu langkah ke task yang sudah ada (generate ulang / perbaikan otomatis)
     * agar dijalankan di container dan percakapan yang sama, lalu antrekan lagi.
     */
    public function appendAndRun(GenerationTask $task, JobStep $step): GenerationTask
    {
        $session = $task->session;
        if ($reason = $this->guard->deny($session->user, $session->module_key)) {
            throw new RuntimeException($reason);
        }
        if (! $task->isFinished()) {
            throw new RuntimeException('Proses sebelumnya untuk dokumen ini masih berjalan.');
        }

        $task->appendStep($step);
        $task->forceFill([
            'status' => GenerationTask::STATUS_QUEUED,
            'error' => null,
            'attempts' => 0,
            'finished_at' => null,
        ])->save();

        $session->refreshStatus();
        RunGenerationTask::dispatch($task->id);

        return $task;
    }

    /** Batalkan task yang masih antre/menunggu (yang sedang berjalan diselesaikan dulu). */
    public function cancel(GenerationSession $session): int
    {
        $n = $session->tasks()
            ->whereIn('status', [GenerationTask::STATUS_QUEUED, GenerationTask::STATUS_WAITING])
            ->update(['status' => GenerationTask::STATUS_CANCELLED, 'finished_at' => now(), 'error' => 'Dibatalkan.']);
        $session->refreshStatus();

        return $n;
    }
}
