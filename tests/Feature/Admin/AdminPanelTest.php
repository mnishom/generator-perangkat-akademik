<?php

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\Prodis;
use App\Livewire\Admin\SystemSettings;
use App\Livewire\Admin\Users;
use App\Models\AccountStatusLog;
use App\Models\AuditLog;
use App\Models\Prodi;
use App\Models\UsageLimit;
use App\Models\User;
use App\Services\Settings;
use Livewire\Livewire;

it('menolak pengguna biasa membuka halaman admin', function (string $url) {
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
})->with(['/admin/pengguna', '/admin/prodi', '/admin/pengaturan', '/admin/audit-log']);

it('mengizinkan admin membuka halaman admin', function (string $url) {
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
})->with(['/admin/pengguna', '/admin/prodi', '/admin/pengaturan', '/admin/audit-log']);

it('menangguhkan akun dengan alasan dan tanggal berakhir, tercatat di log dan audit', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    Livewire::actingAs($admin)->test(Users::class)
        ->call('edit', $user->id)
        ->set('form.status', 'ditangguhkan')
        ->set('form.status_reason', 'Melebihi kuota')
        ->set('form.status_until', now()->addWeek()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->status)->toBe(AccountStatus::Ditangguhkan)
        ->and($user->status_reason)->toBe('Melebihi kuota')
        ->and($user->status_until)->not->toBeNull()
        ->and($user->canLogin())->toBeTrue()
        ->and($user->canGenerate())->toBeFalse();

    expect(AccountStatusLog::where('user_id', $user->id)->first())
        ->to_status->toBe(AccountStatus::Ditangguhkan)
        ->changed_by->toBe($admin->id);
    expect(AuditLog::where('action', 'user.status')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

it('mengaktifkan kembali akun dan menghapus alasan', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->status(AccountStatus::Dinonaktifkan)->create();

    Livewire::actingAs($admin)->test(Users::class)
        ->call('edit', $user->id)
        ->set('form.status', 'aktif')
        ->call('save');

    expect($user->fresh())->status->toBe(AccountStatus::Aktif)->status_reason->toBeNull();
});

it('tidak membuat log status bila status tidak berubah', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    Livewire::actingAs($admin)->test(Users::class)->call('edit', $user->id)->call('save');

    expect(AccountStatusLog::count())->toBe(0);
});

it('mencegah admin menurunkan peran atau menonaktifkan dirinya sendiri', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(Users::class)
        ->call('edit', $admin->id)
        ->set('form.role', 'user')
        ->call('save')
        ->assertHasErrors('form.role');

    expect($admin->fresh()->role)->toBe(UserRole::Admin);
});

it('mengubah peran, mengunci prodi, dan menetapkan batas per user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $prodi = Prodi::factory()->create();

    Livewire::actingAs($admin)->test(Users::class)
        ->call('edit', $user->id)
        ->set('form.role', 'admin')
        ->set('form.prodi_id', $prodi->id)
        ->set('form.prodi_locked', true)
        ->set('form.max_generate_per_day', 5)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->role->toBe(UserRole::Admin)
        ->prodi_id->toBe($prodi->id)
        ->prodi_locked->toBeTrue();
    expect(UsageLimit::where('scope', 'user')->where('user_id', $user->id)->value('max_generate_per_day'))->toBe(5);
});

it('mass assignment tidak dapat mengubah peran', function () {
    $user = User::factory()->create();
    $user->fill(['role' => 'admin', 'status' => 'aktif'])->save();

    expect($user->fresh()->role)->toBe(UserRole::User);
});

it('menyimpan pengaturan sistem, sakelar global, dan batas global', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(SystemSettings::class)
        ->set('form.generate_enabled', false)
        ->set('form.max_concurrent_jobs', 3)
        ->set('form.default_model', 'claude-opus-5-5')
        ->set('limit.max_generate_per_month', 100)
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(Settings::class);
    expect($settings->generateEnabled())->toBeFalse()
        ->and($settings->get('max_concurrent_jobs'))->toBe(3)
        ->and($settings->defaultModel())->toBe('claude-opus-5-5');
    expect(UsageLimit::where('scope', 'global')->value('max_generate_per_month'))->toBe(100);
    expect(AuditLog::where('action', 'settings.update')->exists())->toBeTrue();
});

it('menolak model yang tidak terdaftar', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(SystemSettings::class)
        ->set('form.default_model', 'gpt-x')
        ->call('save')
        ->assertHasErrors('form.default_model');
});

it('mengelola fakultas dan prodi', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(Prodis::class)
        ->call('editFakultas')
        ->set('fakultasForm.nama', 'Fakultas Teknik')
        ->set('fakultasForm.nama_dekan', 'Dr. Dekan')
        ->call('saveFakultas')
        ->assertHasNoErrors()
        ->call('editProdi')
        ->set('prodiForm.nama', 'Teknik Informatika')
        ->set('prodiForm.jenjang', 'D4')
        ->set('prodiForm.kode', 'DTI')
        ->call('saveProdi')
        ->assertHasNoErrors();

    expect(Prodi::firstWhere('kode', 'DTI'))->namaLengkap()->toBe('D4 Teknik Informatika');
    expect(AuditLog::whereIn('action', ['fakultas.create', 'prodi.create'])->count())->toBe(2);
});
