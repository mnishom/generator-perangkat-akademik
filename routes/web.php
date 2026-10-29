<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\LibraryFileDownloadController;
use App\Http\Controllers\ZipDownloadController;
use App\Http\Middleware\EnsureModuleAccess;
use App\Livewire\Admin;
use App\Livewire\Dashboard;
use App\Livewire\Library\DocumentLibraryPage;
use App\Livewire\Library\FileLibraryPage;
use App\Livewire\Modules\ModuleHome;
use App\Livewire\Modules\WizardHost;
use App\Livewire\PilihProdi;
use App\Livewire\Sessions\History;
use App\Livewire\Sessions\SessionShow;
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

        Route::middleware(EnsureModuleAccess::class)->prefix('m/{module}')->name('modules.')->group(function () {
            Route::get('/', ModuleHome::class)->name('show');
            Route::get('/sesi/{session}/wizard', WizardHost::class)->name('wizard');
        });

        Route::get('/riwayat', History::class)->name('history');
        Route::get('/sesi/{session}', SessionShow::class)->name('sessions.show');
        Route::get('/sesi/{session}/zip', [ZipDownloadController::class, 'session'])->name('sessions.zip');
        Route::get('/sesi/{session}/mk/{item}/zip', [ZipDownloadController::class, 'item'])->name('sessions.zip.item');
        Route::get('/dokumen/{document}/unduh', DocumentDownloadController::class)->name('documents.download');

        Route::get('/pustaka-berkas', FileLibraryPage::class)->name('library.files');
        Route::get('/pustaka-berkas/{file}/unduh', LibraryFileDownloadController::class)->name('library.download');
        Route::get('/pustaka-dokumen', DocumentLibraryPage::class)->name('library.documents');
    });

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/pengguna', Admin\Users::class)->name('users');
        Route::get('/prodi', Admin\Prodis::class)->name('prodis');
        Route::get('/modul', Admin\Modules::class)->name('modules');
        Route::get('/pengaturan', Admin\SystemSettings::class)->name('settings');
        Route::get('/audit-log', Admin\AuditLogs::class)->name('audit');
    });
});

if (app()->isLocal() && config('platform.dev_login')) {
    require __DIR__.'/dev.php';
}
