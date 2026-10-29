<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Models\AccountStatusLog;
use App\Models\User;
use App\Services\Audit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ChangeAccountStatus
{
    public function __invoke(User $user, AccountStatus $status, ?string $reason = null, ?CarbonInterface $until = null): void
    {
        DB::transaction(function () use ($user, $status, $reason, $until) {
            $from = $user->status;

            $user->forceFill([
                'status' => $status,
                'status_reason' => $status === AccountStatus::Aktif ? null : $reason,
                'status_until' => $status === AccountStatus::Aktif ? null : $until,
            ])->save();

            AccountStatusLog::create([
                'user_id' => $user->id,
                'changed_by' => auth()->id(),
                'from_status' => $from,
                'to_status' => $status,
                'reason' => $reason,
                'until' => $until,
            ]);

            Audit::log('user.status', $user, [
                'from' => $from?->value, 'to' => $status->value, 'reason' => $reason, 'until' => $until?->toIso8601String(),
            ]);
        });
    }
}
