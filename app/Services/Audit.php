<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/** Pencatat audit log tindakan admin. */
class Audit
{
    public static function log(string $action, ?Model $target = null, array $meta = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id' => $target?->getKey(),
            'meta' => $meta ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
