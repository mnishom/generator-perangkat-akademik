<?php

namespace App\Core\Generation;

use App\Core\Modules\ModuleAccess;
use App\Core\Usage\QuotaService;
use App\Models\User;
use App\Services\Settings;

/**
 * Pemeriksaan sebelum memanggil Claude. Dipanggil saat pengguna memulai
 * generate DAN diulang di dalam Job (kondisi dapat berubah selama antre).
 */
class GenerationGuard
{
    public function __construct(
        private readonly ModuleAccess $access,
        private readonly QuotaService $quota,
        private readonly Settings $settings,
    ) {}

    /** @return string|null alasan penolakan, atau null bila boleh */
    public function deny(User $user, string $moduleKey): ?string
    {
        $user->refresh();

        if (! $user->canLogin()) {
            return 'Akun dinonaktifkan.';
        }
        if (! $user->canGenerate()) {
            return 'Akun sedang ditangguhkan'.($user->status_reason ? ': '.$user->status_reason : '').'.';
        }
        if (! $this->access->canAccess($user, $moduleKey)) {
            return 'Anda tidak memiliki akses ke modul ini.';
        }
        if (! $this->settings->generateEnabled()) {
            return (string) $this->settings->get('generate_closed_message');
        }
        if (! $this->access->generateOpen($moduleKey)) {
            return 'Generate pada modul ini sedang ditutup sementara oleh admin.';
        }

        return $this->quota->exceeded($user, $moduleKey);
    }
}
