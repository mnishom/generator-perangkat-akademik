<?php

namespace App\Models;

use App\Core\Modules\JobStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenerationTask extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    /** Menunggu slot (batas Job bersamaan) atau dijeda sementara. */
    public const STATUS_WAITING = 'waiting';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    /** Ditolak pemeriksaan (status akun, akses modul, kuota, sakelar). */
    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'generation_session_id', 'generation_item_id', 'key', 'label', 'steps', 'skills', 'current_step', 'status',
        'model', 'container_id', 'container_expires_at', 'conversation', 'attempts', 'error',
        'input_tokens', 'output_tokens', 'heartbeat_at', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'skills' => 'array',
            'conversation' => 'array',
            'container_expires_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GenerationSession::class, 'generation_session_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GenerationItem::class, 'generation_item_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class);
    }

    /** @return JobStep[] */
    public function jobSteps(): array
    {
        return array_map(fn (array $s) => JobStep::fromArray($s), $this->steps ?? []);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED, self::STATUS_BLOCKED, self::STATUS_CANCELLED], true);
    }

    public function containerUsable(): bool
    {
        return $this->container_id && (! $this->container_expires_at || $this->container_expires_at->isAfter(now()->addMinutes(10)));
    }

    /** Tambahkan langkah baru (mis. perbaikan otomatis / generate ulang) di akhir Job. */
    public function appendStep(JobStep $step): void
    {
        $steps = $this->steps ?? [];
        $steps[] = $step->toArray();
        $this->steps = $steps;
    }

    public function progressLabel(): string
    {
        $total = count($this->steps ?? []);

        return min($this->current_step + ($this->status === self::STATUS_RUNNING ? 1 : 0), $total).'/'.$total;
    }
}
