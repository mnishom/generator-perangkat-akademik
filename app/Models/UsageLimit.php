<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLimit extends Model
{
    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_MODULE = 'module';

    public const SCOPE_USER = 'user';

    protected $fillable = ['scope', 'module_key', 'user_id', 'max_generate_per_day', 'max_generate_per_month', 'max_tokens_per_month'];

    protected function casts(): array
    {
        return [
            'max_generate_per_day' => 'integer',
            'max_generate_per_month' => 'integer',
            'max_tokens_per_month' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forScope(string $scope, ?string $moduleKey = null, ?int $userId = null): self
    {
        return static::firstOrNew(['scope' => $scope, 'module_key' => $moduleKey, 'user_id' => $userId]);
    }
}
