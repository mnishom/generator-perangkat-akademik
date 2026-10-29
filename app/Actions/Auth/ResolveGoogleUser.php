<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Exceptions\LoginRejected;
use App\Models\User;
use App\Support\EmailDomainPolicy;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Memvalidasi akun Google dan mencari/membuat pengguna aplikasi.
 */
class ResolveGoogleUser
{
    public function __invoke(SocialiteUser $googleUser): User
    {
        $policy = EmailDomainPolicy::fromConfig();
        $raw = method_exists($googleUser, 'getRaw') ? (array) $googleUser->getRaw() : [];
        $email = Str::lower(trim((string) $googleUser->getEmail()));

        $rejected = 'Hanya akun Google @'.$policy->domain().' yang dapat masuk.';

        if (! $policy->hostedDomainAllowed($raw['hd'] ?? null)) {
            throw new LoginRejected($rejected);
        }

        if (! $policy->emailAllowed($email)) {
            throw new LoginRejected($rejected);
        }

        if (array_key_exists('email_verified', $raw) && ! filter_var($raw['email_verified'], FILTER_VALIDATE_BOOL)) {
            throw new LoginRejected('Email Google Anda belum terverifikasi.');
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $email)->first();

        if (! $user) {
            $user = new User;
            $user->role = in_array($email, array_map('strtolower', config('platform.admin_emails')), true)
                ? UserRole::Admin
                : UserRole::User;
        }

        if (! $user->exists || $user->canLogin()) {
            $user->fill([
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'last_login_at' => now(),
            ]);
            $user->email_verified_at ??= now();
            $user->save();
        }

        if (! $user->canLogin()) {
            throw new LoginRejected($this->disabledMessage($user));
        }

        return $user;
    }

    private function disabledMessage(User $user): string
    {
        $msg = 'Akun Anda dinonaktifkan';
        if ($user->status_reason) {
            $msg .= ': '.$user->status_reason;
        }
        if ($user->status_until) {
            $msg .= ' (hingga '.$user->status_until->translatedFormat('d F Y H:i').')';
        }

        return $msg.'. Hubungi admin bila ini keliru.';
    }
}
