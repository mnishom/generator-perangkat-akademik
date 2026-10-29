<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'prodi_id', 'module_key', 'generation_session_id', 'generation_task_id', 'kind', 'model',
        'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens', 'cost_usd',
    ];

    protected function casts(): array
    {
        return ['cost_usd' => 'float', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function totalTokens(): int
    {
        return $this->input_tokens + $this->output_tokens + $this->cache_read_tokens + $this->cache_write_tokens;
    }
}
