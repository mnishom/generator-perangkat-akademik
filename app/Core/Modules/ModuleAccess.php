<?php

namespace App\Core\Modules;

use App\Models\ModuleAccessRule;
use App\Models\ModuleState;
use App\Models\User;
use App\Services\Settings;

/**
 * Menentukan apakah seorang pengguna dapat membuka dan menjalankan generate di sebuah modul.
 * Urutan prioritas akses: aturan per user > aturan per prodi > status global modul.
 */
class ModuleAccess
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Settings $settings,
    ) {}

    public function canAccess(User $user, string $moduleKey): bool
    {
        if (! $this->registry->has($moduleKey)) {
            return false;
        }

        $rules = ModuleAccessRule::where('module_key', $moduleKey)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('scope', ModuleAccessRule::SCOPE_USER)->where('scope_id', $user->id))
                ->orWhere(fn ($q) => $q->where('scope', ModuleAccessRule::SCOPE_PRODI)->where('scope_id', $user->prodi_id ?? 0)))
            ->get()
            ->keyBy('scope');

        if ($rule = $rules->get(ModuleAccessRule::SCOPE_USER)) {
            return $rule->allowed;
        }
        if ($rule = $rules->get(ModuleAccessRule::SCOPE_PRODI)) {
            return $rule->allowed;
        }

        return ModuleState::for($moduleKey)->enabled;
    }

    /** @return array<string, ModuleContract> modul yang dapat dibuka pengguna */
    public function accessibleModules(User $user): array
    {
        return array_filter($this->registry->all(), fn (ModuleContract $m) => $this->canAccess($user, $m->key()));
    }

    /** Sakelar global dan sakelar modul untuk generate. */
    public function generateOpen(string $moduleKey): bool
    {
        return $this->settings->generateEnabled() && ModuleState::for($moduleKey)->generate_open;
    }

    /** Model efektif untuk modul: pilihan admin per modul, lalu default sistem. */
    public function modelFor(string $moduleKey): string
    {
        $model = ModuleState::for($moduleKey)->model;

        return $model && array_key_exists($model, config('claude.models'))
            ? $model
            : $this->settings->defaultModel();
    }
}
