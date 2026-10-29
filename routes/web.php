<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Livewire\Admin;
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

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/pengguna', Admin\Users::class)->name('users');
        Route::get('/prodi', Admin\Prodis::class)->name('prodis');
        Route::get('/pengaturan', Admin\SystemSettings::class)->name('settings');
        Route::get('/audit-log', Admin\AuditLogs::class)->name('audit');
    });
});

if (app()->isLocal() && config('platform.dev_login')) {
    require __DIR__.'/dev.php';
}
