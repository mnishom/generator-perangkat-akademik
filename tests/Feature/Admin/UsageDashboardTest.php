<?php

use App\Core\Generation\SessionLauncher;
use App\Livewire\Admin\Prompts;
use App\Livewire\Admin\UsageDashboard;
use App\Models\AiUsage;
use App\Models\AuditLog;
use App\Models\GenerationTask;
use App\Models\User;
use App\Modules\PerangkatPembelajaran\PerangkatPembelajaranModule as Module;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

require_once __DIR__.'/../PerangkatPembelajaran/Helpers.php';

beforeEach(function () {
    Storage::fake('local');
    registerModules(Module::class);
});

it('menampilkan ringkasan pemakaian per modul, pengguna, dan prodi', function () {
    $admin = User::factory()->admin()->create();
    $u = User::factory()->create(['name' => 'Dosen Satu']);
    AiUsage::create(['user_id' => $u->id, 'prodi_id' => $u->prodi_id, 'module_key' => Module::KEY, 'kind' => 'generate', 'model' => 'claude-sonnet-5-5', 'input_tokens' => 1_000_000, 'output_tokens' => 100_000, 'cost_usd' => 3.0]);
    AiUsage::create(['user_id' => $u->id, 'prodi_id' => $u->prodi_id, 'module_key' => Module::KEY, 'kind' => 'extract', 'model' => 'claude-sonnet-5-5', 'input_tokens' => 10, 'output_tokens' => 10, 'cost_usd' => 0.5]);

    Livewire::actingAs($admin)->test(UsageDashboard::class)
        ->assertSee('Perangkat Pembelajaran')->assertSee('$3.50')->assertSee('1.000.010')
        ->set('group', 'user')->assertSee('Dosen Satu')
        ->set('group', 'prodi')->assertSee($u->prodi->nama);
    $this->actingAs($u)->get('/admin')->assertForbidden();
});

it('admin dapat membatalkan Job antre dan tercatat di audit log', function () {
    Queue::fake();
    $u = User::factory()->create();
    [$t1] = app(SessionLauncher::class)->launch(ppSession($u, silabus: false));

    Livewire::actingAs(User::factory()->admin()->create())->test(UsageDashboard::class)->call('cancelTask', $t1->id);

    expect($t1->fresh()->status)->toBe(GenerationTask::STATUS_CANCELLED)
        ->and(AuditLog::where('action', 'task.cancel')->exists())->toBeTrue();
});

it('admin menyunting prompt default dengan versi dan rollback dari panel', function () {
    $admin = User::factory()->admin()->create();
    Livewire::actingAs($admin)->test(Prompts::class)
        ->assertSet('module', Module::KEY)->assertSet('key', 'rps_kontrak')
        ->set('body', 'Prompt baru {{nama_mk}}')->assertSee('Variabel penting hilang')
        ->call('save')
        ->assertSee('Versi 2')
        ->call('activate', 1)
        ->assertSet('body', file_get_contents(resource_path('prompts/rps_kontrak.md')));

    expect(AuditLog::whereIn('action', ['prompt.save', 'prompt.rollback'])->count())->toBe(2);
});
