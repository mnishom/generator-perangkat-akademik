<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prompt extends Model
{
    public const KIND_DEFAULT = 'default';

    public const KIND_PERSONAL = 'personal';

    protected $fillable = ['module_key', 'prompt_key', 'kind', 'user_id', 'version', 'body', 'is_active', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
