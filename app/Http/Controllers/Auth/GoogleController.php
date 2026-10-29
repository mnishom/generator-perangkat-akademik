<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveGoogleUser;
use App\Exceptions\LoginRejected;
use App\Http\Controllers\Controller;
use App\Support\EmailDomainPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        // `hd` hanya petunjuk tampilan bagi Google; validasi sesungguhnya ada di callback.
        return Socialite::driver('google')
            ->with(['hd' => EmailDomainPolicy::fromConfig()->domain(), 'prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(ResolveGoogleUser $resolve): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Login Google gagal', ['error' => $e->getMessage()]);

            return redirect()->route('login')->with('error', 'Login Google gagal atau dibatalkan. Silakan coba lagi.');
        }

        try {
            $user = $resolve($googleUser);
        } catch (LoginRejected $e) {
            return redirect()->route('login')->with('error', $e->getMessage());
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
