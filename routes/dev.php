<?php

// Login cepat untuk pengembangan lokal tanpa Google. Aktif hanya bila
// APP_ENV=local DAN DEV_LOGIN=true; tidak pernah dimuat di produksi.

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/dev/login/{user}', function (User $user) {
    Auth::login($user);
    request()->session()->regenerate();

    return redirect()->route('dashboard');
})->name('dev.login');
