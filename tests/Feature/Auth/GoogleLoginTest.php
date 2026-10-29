<?php

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Livewire\PilihProdi;
use App\Models\Prodi;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGoogleUser(string $email, ?string $hd = 'harkatnegeri.ac.id', string $id = '1001', array $extra = []): void
{
    $raw = array_filter(array_merge(['hd' => $hd, 'email' => $email, 'email_verified' => true], $extra), fn ($v) => $v !== null);

    $user = (new SocialiteUser)->setRaw($raw)->map([
        'id' => $id,
        'name' => 'Dosen Uji',
        'email' => $email,
        'avatar' => 'https://example.com/a.png',
    ]);

    Socialite::shouldReceive('driver->user')->andReturn($user);
}

it('menampilkan halaman login', function () {
    $this->get('/login')->assertOk()->assertSee('Masuk dengan Google');
});

it('mengalihkan tamu ke login', function () {
    $this->get('/')->assertRedirect('/login');
});

it('membuat akun baru berperan user untuk email kampus', function () {
    fakeGoogleUser('dosen@harkatnegeri.ac.id');

    $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

    $user = User::firstWhere('email', 'dosen@harkatnegeri.ac.id');
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::User)
        ->and($user->status)->toBe(AccountStatus::Aktif)
        ->and($user->google_id)->toBe('1001');
    $this->assertAuthenticatedAs($user);
});

it('menolak email di luar domain kampus', function () {
    fakeGoogleUser('orang@gmail.com', hd: null);

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHas('error');
    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('menolak bila klaim hd Google tidak sesuai walau email berakhiran kampus', function () {
    fakeGoogleUser('dosen@harkatnegeri.ac.id', hd: 'lain.ac.id');

    $this->get('/auth/google/callback')->assertRedirect('/login');
    $this->assertGuest();
});

it('menolak bila hd benar tetapi alamat email bukan domain kampus', function () {
    fakeGoogleUser('dosen@harkatnegeri.ac.id.evil.com', hd: 'harkatnegeri.ac.id');

    $this->get('/auth/google/callback')->assertRedirect('/login');
    $this->assertGuest();
});

it('menolak subdomain atau domain mirip', function () {
    fakeGoogleUser('dosen@mhs.harkatnegeri.ac.id', hd: 'mhs.harkatnegeri.ac.id');

    $this->get('/auth/google/callback')->assertRedirect('/login');
    $this->assertGuest();
});

it('menolak email yang belum terverifikasi', function () {
    fakeGoogleUser('dosen@harkatnegeri.ac.id', extra: ['email_verified' => false]);

    $this->get('/auth/google/callback')->assertRedirect('/login');
    $this->assertGuest();
});

it('menjadikan email di ADMIN_EMAILS sebagai admin', function () {
    fakeGoogleUser('admin@harkatnegeri.ac.id');

    $this->get('/auth/google/callback');

    expect(User::firstWhere('email', 'admin@harkatnegeri.ac.id')->role)->toBe(UserRole::Admin);
});

it('tidak menurunkan peran admin yang sudah ada', function () {
    $user = User::factory()->admin()->create(['email' => 'lama@harkatnegeri.ac.id', 'google_id' => '1001']);
    fakeGoogleUser('lama@harkatnegeri.ac.id');

    $this->get('/auth/google/callback');

    expect($user->fresh()->role)->toBe(UserRole::Admin);
});

it('menolak akun dinonaktifkan', function () {
    User::factory()->status(AccountStatus::Dinonaktifkan)->create(['email' => 'off@harkatnegeri.ac.id', 'google_id' => '1001']);
    fakeGoogleUser('off@harkatnegeri.ac.id');

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHas('error');
    $this->assertGuest();
});

it('mengizinkan akun dinonaktifkan yang masa berlakunya sudah lewat', function () {
    $user = User::factory()->status(AccountStatus::Dinonaktifkan)->create([
        'email' => 'off@harkatnegeri.ac.id', 'google_id' => '1001',
    ]);
    $user->forceFill(['status_until' => now()->subDay()])->save();
    fakeGoogleUser('off@harkatnegeri.ac.id');

    $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

it('mengizinkan akun ditangguhkan login', function () {
    User::factory()->status(AccountStatus::Ditangguhkan)->create(['email' => 'tunda@harkatnegeri.ac.id', 'google_id' => '1001']);
    fakeGoogleUser('tunda@harkatnegeri.ac.id');

    $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

it('mengeluarkan pengguna yang dinonaktifkan saat sesi berjalan', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/')->assertOk();

    $user->forceFill(['status' => AccountStatus::Dinonaktifkan])->save();

    $this->actingAs($user->fresh())->get('/')->assertRedirect('/login');
    $this->assertGuest();
});

it('meminta pengguna baru memilih prodi', function () {
    $user = User::factory()->withoutProdi()->create();

    $this->actingAs($user)->get('/')->assertRedirect(route('prodi.select'));
});

it('menyimpan pilihan prodi', function () {
    $user = User::factory()->withoutProdi()->create();
    $prodi = Prodi::factory()->create();

    Livewire\Livewire::actingAs($user)
        ->test(PilihProdi::class)
        ->set('prodiId', $prodi->id)
        ->call('save')
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->prodi_id)->toBe($prodi->id);
});

it('tidak mengizinkan ganti prodi bila dikunci admin', function () {
    $user = User::factory()->create();
    $user->forceFill(['prodi_locked' => true])->save();
    $other = Prodi::factory()->create();

    Livewire\Livewire::actingAs($user)
        ->test(PilihProdi::class)
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->prodi_id)->not->toBe($other->id);
});

it('logout', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
