<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Livewire\Dashboard;
use App\Livewire\PilihProdi;
use Illuminate\Support\Facades\Route;

Route::view('/privasi', 'pages.privasi')->name('privasi');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [GoogleController::class, 'logout'])->name('logout');
    Route::get('/pilih-prodi', PilihProdi::class)->name('prodi.select');

    Route::middleware('prodi')->group(function () {
        Route::get('/', Dashboard::class)->name('dashboard');
    });
});

if (app()->isLocal() && config('platform.dev_login')) {
    require __DIR__.'/dev.php';
}
