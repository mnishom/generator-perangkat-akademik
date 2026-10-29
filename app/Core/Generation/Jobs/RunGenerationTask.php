<?php

namespace App\Core\Generation\Jobs;

use App\Core\Claude\ClaudeException;
use App\Core\Generation\GenerationRunner;
use App\Models\GenerationTask;
use App\Services\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Job antrean untuk satu GenerationTask. Dapat dijalankan ulang kapan saja:
 * langkah yang sudah selesai tidak diulang.
 */
class RunGenerationTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Detik maksimum satu eksekusi Job (beberapa panggilan generate berurutan). */
    public int $timeout = 1200;

    /** Galat nyata maksimum; penundaan karena slot penuh tidak dihitung sebagai galat. */
    public int $maxExceptions = 5;

    public bool $failOnTimeout = false;

    public function __construct(public readonly int $taskId) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(12);
    }

    public function handle(GenerationRunner $runner, Settings $settings): void
    {
        $task = GenerationTask::find($this->taskId);
        if (! $task || $task->isFinished()) {
            return;
        }

        if (! $this->claimSlot($task, (int) $settings->get('max_concurrent_jobs', 2))) {
            $this->release(30);

            return;
        }

        try {
            $runner->run($task);
        } catch (ClaudeException $e) {
            if ($e->transient) {
                $task->increment('attempts');
                if ($task->attempts >= 6) {
                    $runner->finish($task, GenerationTask::STATUS_FAILED, 'Gagal setelah beberapa kali percobaan: '.$e->getMessage());

                    return;
                }
                $this->release($e->retryAfter ?: min(600, 30 * (2 ** $task->attempts)));

                return;
            }
            throw $e;
        }
    }

    /**
     * Batasi jumlah task yang berjalan bersamaan. Task "running" tanpa heartbeat
     * 25 menit dianggap terputus dan tidak dihitung.
     */
    private function claimSlot(GenerationTask $task, int $max): bool
    {
        return Cache::lock('generation-slots', 15)->block(15, function () use ($task, $max) {
            $running = GenerationTask::query()
                ->where('status', GenerationTask::STATUS_RUNNING)
                ->where('id', '!=', $task->id)
                ->where('heartbeat_at', '>', now()->subMinutes(25))
                ->count();

            if ($running >= max(1, $max)) {
                if ($task->status !== GenerationTask::STATUS_WAITING) {
                    $task->forceFill(['status' => GenerationTask::STATUS_WAITING])->save();
                }

                return false;
            }

            $task->forceFill(['status' => GenerationTask::STATUS_RUNNING, 'heartbeat_at' => now()])->save();

            return true;
        });
    }

    public function failed(?Throwable $e): void
    {
        $task = GenerationTask::find($this->taskId);
        if ($task && ! $task->isFinished()) {
            app(GenerationRunner::class)->finish($task, GenerationTask::STATUS_FAILED, $e?->getMessage() ?? 'Job gagal.');
        }
    }
}
