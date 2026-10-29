<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumExtraction extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_EXTRACTED = 'extracted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'library_file_id', 'sha256', 'prodi_id', 'user_id', 'module_key', 'status', 'raw', 'data', 'anomalies',
        'error', 'model', 'input_tokens', 'output_tokens', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'anomalies' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(LibraryFile::class, 'library_file_id')->withTrashed();
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isWorking(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    /** @return array<int, array> */
    public function courses(): array
    {
        return $this->data['mata_kuliah'] ?? [];
    }

    public function course(string $kode): ?array
    {
        foreach ($this->courses() as $mk) {
            if (($mk['kode'] ?? null) === $kode) {
                return $mk;
            }
        }

        return null;
    }
}
