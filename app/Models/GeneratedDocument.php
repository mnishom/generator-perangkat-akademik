<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedDocument extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_ATTENTION = 'perlu_perhatian';

    public const STATUS_FAILED = 'gagal';

    protected $fillable = [
        'generation_session_id', 'generation_item_id', 'generation_task_id', 'user_id', 'prodi_id', 'module_key',
        'doc_type', 'format', 'version', 'parent_id', 'ref_code', 'path', 'filename', 'size', 'anthropic_file_id',
        'report', 'checks', 'status', 'autofix_count', 'instruction', 'model', 'input_tokens', 'output_tokens', 'is_final',
    ];

    protected function casts(): array
    {
        return ['report' => 'array', 'checks' => 'array', 'is_final' => 'boolean'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GenerationSession::class, 'generation_session_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GenerationItem::class, 'generation_item_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(GenerationTask::class, 'generation_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Hanya versi terbaru per (sesi, item, jenis dokumen). */
    public function scopeLatestVersions(Builder $q): Builder
    {
        return $q->whereNotExists(fn ($sub) => $sub->from('generated_documents as newer')
            ->whereColumn('newer.generation_session_id', 'generated_documents.generation_session_id')
            ->whereColumn('newer.doc_type', 'generated_documents.doc_type')
            ->whereRaw('coalesce(newer.generation_item_id, 0) = coalesce(generated_documents.generation_item_id, 0)')
            ->whereColumn('newer.version', '>', 'generated_documents.version'));
    }

    /** @return array<int, array{key: string, label: string, passed: bool, message: string}> */
    public function failedChecks(): array
    {
        return array_values(array_filter($this->checks ?? [], fn ($c) => ! ($c['passed'] ?? true)));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_OK => 'Lolos pemeriksaan',
            self::STATUS_ATTENTION => 'Perlu Perhatian',
            self::STATUS_FAILED => 'Gagal',
            default => $this->status,
        };
    }
}
