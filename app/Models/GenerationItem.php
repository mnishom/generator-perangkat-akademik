<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenerationItem extends Model
{
    protected $fillable = ['generation_session_id', 'ref_code', 'ref_label', 'input', 'selected', 'status', 'sort'];

    protected function casts(): array
    {
        return ['input' => 'array', 'selected' => 'boolean'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GenerationSession::class, 'generation_session_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(GenerationTask::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class);
    }
}
