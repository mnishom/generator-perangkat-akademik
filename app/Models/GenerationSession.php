<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenerationSession extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_ATTENTION = 'attention';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'module_key', 'user_id', 'prodi_id', 'curriculum_extraction_id', 'title', 'tanggal_penyusunan',
        'tahun_akademik', 'identity', 'inputs', 'prompts', 'options', 'wizard_step', 'model', 'status',
        'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_penyusunan' => 'date',
            'identity' => 'array',
            'inputs' => 'array',
            'prompts' => 'array',
            'options' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GenerationItem::class)->orderBy('sort')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(GenerationTask::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class);
    }

    public function scopeOwnedBy(Builder $q, User $user): Builder
    {
        return $q->where('user_id', $user->id);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return data_get($this->options, $key, $default);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    /** Hitung ulang status sesi dari status task-tasknya. */
    public function refreshStatus(): void
    {
        $statuses = $this->tasks()->pluck('status');
        if ($statuses->isEmpty()) {
            return;
        }

        $active = $statuses->intersect([GenerationTask::STATUS_QUEUED, GenerationTask::STATUS_RUNNING, GenerationTask::STATUS_WAITING]);
        $status = match (true) {
            $active->isNotEmpty() => $statuses->contains(GenerationTask::STATUS_RUNNING) ? self::STATUS_RUNNING : self::STATUS_QUEUED,
            $statuses->every(fn ($s) => $s === GenerationTask::STATUS_CANCELLED) => self::STATUS_CANCELLED,
            $statuses->contains(GenerationTask::STATUS_FAILED) || $statuses->contains(GenerationTask::STATUS_BLOCKED) => self::STATUS_FAILED,
            $this->documents()->where('status', GeneratedDocument::STATUS_ATTENTION)->exists() => self::STATUS_ATTENTION,
            default => self::STATUS_DONE,
        };

        $this->status = $status;
        if (in_array($status, [self::STATUS_DONE, self::STATUS_ATTENTION, self::STATUS_FAILED, self::STATUS_CANCELLED], true)) {
            $this->finished_at ??= now();
        } else {
            $this->finished_at = null;
        }
        $this->save();
    }
}
