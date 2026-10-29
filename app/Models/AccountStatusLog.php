<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountStatusLog extends Model
{
    protected $fillable = ['user_id', 'changed_by', 'from_status', 'to_status', 'reason', 'until'];

    protected function casts(): array
    {
        return [
            'from_status' => AccountStatus::class,
            'to_status' => AccountStatus::class,
            'until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
