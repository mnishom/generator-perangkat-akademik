<?php

namespace App\Core\Curriculum\Jobs;

use App\Core\Claude\ClaudeException;
use App\Core\Curriculum\CurriculumExtractor;
use App\Models\CurriculumExtraction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class ExtractCurriculum implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1200;

    public int $tries = 6;

    public bool $failOnTimeout = false;

    public function __construct(public readonly int $extractionId) {}

    public function handle(CurriculumExtractor $extractor): void
    {
        $extraction = CurriculumExtraction::find($this->extractionId);
        if (! $extraction || ! in_array($extraction->status, [CurriculumExtraction::STATUS_QUEUED, CurriculumExtraction::STATUS_RUNNING], true)) {
            return;
        }

        try {
            $extractor->run($extraction);
        } catch (ClaudeException $e) {
            if ($e->transient && $this->attempts() < $this->tries) {
                $extraction->update(['status' => CurriculumExtraction::STATUS_QUEUED, 'error' => 'Dicoba ulang: '.$e->getMessage()]);
                $this->release($e->retryAfter ?: 30 * (2 ** $this->attempts()));

                return;
            }
            $extraction->update(['status' => CurriculumExtraction::STATUS_FAILED, 'error' => $e->getMessage()]);
        }
    }

    public function failed(?Throwable $e): void
    {
        CurriculumExtraction::whereKey($this->extractionId)
            ->whereIn('status', [CurriculumExtraction::STATUS_QUEUED, CurriculumExtraction::STATUS_RUNNING])
            ->update(['status' => CurriculumExtraction::STATUS_FAILED, 'error' => $e?->getMessage() ?? 'Job gagal.']);
    }
}
