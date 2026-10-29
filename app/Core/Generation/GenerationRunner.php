<?php

namespace App\Core\Generation;

use App\Core\Claude\ClaudeDocumentClient;
use App\Core\Claude\ClaudeException;
use App\Core\Claude\GenerateRequest;
use App\Core\Claude\GenerateResult;
use App\Core\Library\FileLibrary;
use App\Core\Modules\JobStep;
use App\Core\Modules\ModuleRegistry;
use App\Core\Usage\UsageRecorder;
use App\Models\GenerationSession;
use App\Models\GenerationTask;
use App\Services\Settings;

/**
 * Pelaksana Job generik: menjalankan langkah-langkah satu task secara berurutan
 * di container yang sama. Setiap langkah yang selesai langsung disimpan
 * (current_step, container, percakapan), sehingga proses yang terputus
 * dilanjutkan dari langkah terakhir, bukan dari awal.
 *
 * Tidak berisi logika khusus jenis dokumen apa pun.
 */
class GenerationRunner
{
    public function __construct(
        private readonly ClaudeDocumentClient $client,
        private readonly ModuleRegistry $modules,
        private readonly GenerationGuard $guard,
        private readonly FileLibrary $files,
        private readonly DocumentStore $documents,
        private readonly UsageRecorder $usage,
        private readonly Settings $settings,
    ) {}

    /**
     * @throws ClaudeException bila galat sementara (agar Job dicoba ulang)
     */
    public function run(GenerationTask $task): void
    {
        $task->refresh();
        if ($task->isFinished()) {
            return;
        }

        $session = $task->session;
        $module = $this->modules->get($session->module_key);

        // Pemeriksaan ulang di dalam Job: status akun, akses modul, sakelar, kuota.
        if ($reason = $this->guard->deny($session->user, $module->key())) {
            $this->finish($task, GenerationTask::STATUS_BLOCKED, $reason);

            return;
        }

        $task->forceFill([
            'status' => GenerationTask::STATUS_RUNNING,
            'started_at' => $task->started_at ?? now(),
            'heartbeat_at' => now(),
            'error' => null,
        ])->save();
        $session->refreshStatus();

        $steps = $task->jobSteps();
        $start = $task->current_step;

        for ($i = $start; $i < count($steps); $i++) {
            if ($i > $start && ($reason = $this->guard->deny($session->user, $module->key()))) {
                $this->finish($task, GenerationTask::STATUS_BLOCKED, $reason);

                return;
            }

            $step = $steps[$i];

            try {
                $result = $this->callStep($task, $session, $steps, $step);
            } catch (ClaudeException $e) {
                if ($e->transient) {
                    $task->forceFill(['status' => GenerationTask::STATUS_WAITING, 'error' => $e->getMessage()])->save();
                    throw $e;
                }
                $this->finish($task, GenerationTask::STATUS_FAILED, "Langkah \"{$step->label}\" gagal: ".$e->getMessage());

                return;
            }

            $this->usage->record($result, $task->model, [
                'user_id' => $session->user_id,
                'prodi_id' => $session->prodi_id,
                'module_key' => $module->key(),
                'generation_session_id' => $session->id,
                'generation_task_id' => $task->id,
                'kind' => 'generate',
            ]);

            $task->forceFill([
                'container_id' => $result->containerId,
                'container_expires_at' => $result->containerExpiresAt,
                'conversation' => $result->messages,
                'input_tokens' => $task->input_tokens + $result->inputTokens(),
                'output_tokens' => $task->output_tokens + $result->outputTokens(),
                'heartbeat_at' => now(),
            ])->save();

            if ($step->collectsOutput && $step->docType) {
                $this->documents->storeFromResult($task, $module, $step, $result);
            }

            $task->forceFill(['current_step' => $i + 1])->save();
        }

        $this->finish($task, GenerationTask::STATUS_DONE);
    }

    /**
     * Satu panggilan generate. Container baru → unggah berkas masukan dan mulai percakapan baru;
     * container masih berlaku → lanjutkan percakapan di container yang sama.
     *
     * @param  JobStep[]  $steps
     */
    private function callStep(GenerationTask $task, GenerationSession $session, array $steps, JobStep $step, bool $retried = false): GenerateResult
    {
        $reuse = $task->containerUsable() && ! empty($task->conversation);
        $messages = $reuse ? $task->conversation : [];
        $content = [];

        if (! $reuse) {
            // Container baru: unggah semua berkas yang dibutuhkan task ini sekaligus.
            $roles = array_values(array_unique(array_merge(...array_map(fn (JobStep $s) => $s->inputRoles, $steps))));
            $fileIds = $roles ? $this->files->ensureRemote($session, $roles) : [];
            $content = ClaudeDocumentClient::containerUploads($fileIds);
            if ($task->current_step > 0) {
                $content[] = ['type' => 'text', 'text' => 'Catatan: proses sebelumnya terputus dan dilanjutkan di lingkungan baru. Kerjakan permintaan berikut secara mandiri berdasarkan berkas terlampir.'];
            }
        }
        $content[] = ['type' => 'text', 'text' => $step->prompt];
        $messages[] = ['role' => 'user', 'content' => $content];

        $request = new GenerateRequest(
            model: $task->model,
            messages: $messages,
            system: $step->system,
            skills: $task->skills ?: ['docx'],
            containerId: $reuse ? $task->container_id : null,
        );

        try {
            return $this->client->generate($request);
        } catch (ClaudeException $e) {
            if ($reuse && ! $retried && $e->isContainerExpired()) {
                // Container kedaluwarsa: mulai ulang di container baru (sekali).
                $task->forceFill(['container_id' => null, 'container_expires_at' => null, 'conversation' => null])->save();
                $session->options = array_merge($session->options ?? [], ['remote_files' => []]);
                $session->save();

                return $this->callStep($task, $session, $steps, $step, true);
            }
            throw $e;
        }
    }

    public function finish(GenerationTask $task, string $status, ?string $error = null): void
    {
        $task->forceFill([
            'status' => $status,
            'error' => $error,
            'finished_at' => now(),
        ])->save();

        $session = $task->session()->first();
        $session->refreshStatus();

        $pending = $session->tasks()->whereNotIn('status', [
            GenerationTask::STATUS_DONE, GenerationTask::STATUS_FAILED, GenerationTask::STATUS_BLOCKED, GenerationTask::STATUS_CANCELLED,
        ])->exists();

        if (! $pending && $this->settings->get('delete_remote_files')) {
            $this->files->purgeRemote($session);
        }
    }
}
