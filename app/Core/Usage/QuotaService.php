<?php

namespace App\Core\Usage;

use App\Models\AiUsage;
use App\Models\UsageLimit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Batas pemakaian. Batas global dan per modul berlaku untuk total seluruh
 * pengguna (anggaran sistem); batas per user berlaku untuk pengguna itu.
 * Satu "generate" = satu panggilan generate yang tercatat di ai_usages (kind=generate).
 */
class QuotaService
{
    /** @return string|null pesan bila batas terlampaui */
    public function exceeded(User $user, string $moduleKey): ?string
    {
        $checks = [
            ['Batas global', UsageLimit::forScope(UsageLimit::SCOPE_GLOBAL), fn (Builder $q) => $q],
            ['Batas modul', UsageLimit::forScope(UsageLimit::SCOPE_MODULE, $moduleKey), fn (Builder $q) => $q->where('module_key', $moduleKey)],
            ['Batas Anda', UsageLimit::forScope(UsageLimit::SCOPE_USER, null, $user->id), fn (Builder $q) => $q->where('user_id', $user->id)],
        ];

        foreach ($checks as [$label, $limit, $scope]) {
            if (! $limit->exists) {
                continue;
            }
            $base = fn () => $scope(AiUsage::query()->where('kind', 'generate'));

            if ($limit->max_generate_per_day !== null
                && $base()->where('created_at', '>=', now()->startOfDay())->count() >= $limit->max_generate_per_day) {
                return "{$label}: jumlah generate per hari ({$limit->max_generate_per_day}) sudah tercapai.";
            }
            if ($limit->max_generate_per_month !== null
                && $base()->where('created_at', '>=', now()->startOfMonth())->count() >= $limit->max_generate_per_month) {
                return "{$label}: jumlah generate per bulan ({$limit->max_generate_per_month}) sudah tercapai.";
            }
            if ($limit->max_tokens_per_month !== null) {
                $tokens = (int) $scope(AiUsage::query())->where('created_at', '>=', now()->startOfMonth())
                    ->selectRaw('coalesce(sum(input_tokens + output_tokens + cache_read_tokens + cache_write_tokens), 0) as t')
                    ->value('t');
                if ($tokens >= $limit->max_tokens_per_month) {
                    return "{$label}: batas token per bulan (".number_format($limit->max_tokens_per_month, 0, ',', '.').') sudah tercapai.';
                }
            }
        }

        return null;
    }
}
