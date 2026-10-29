<?php

use App\Core\Claude\ClaudeException;
use App\Core\Generation\GenerationRunner;
use App\Core\Generation\Jobs\RunGenerationTask;
use App\Core\Generation\SessionLauncher;
use App\Core\Modules\JobStep;
use App\Enums\AccountStatus;
use App\Models\AiUsage;
use App\Models\GeneratedDocument;
use App\Models\GenerationSession;
use App\Models\GenerationTask;
use App\Models\ModuleAccessRule;
use App\Models\ModuleState;
use App\Models\UsageLimit;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DummyModule;
use Tests\Support\FakeClaudeDocumentClient as Fake;

beforeEach(function () {
    Storage::fake('local');
    registerModules(DummyModule::class);
    $this->user = User::factory()->create();
});

function okResponse(string $file, string $kode): array
{
    return ['text' => "Selesai.\n```json\n{\"kode\": \"{$kode}\"}\n```", 'files' => [$file => Fake::filler("Dokumen {$kode}")]];
}

it('menampilkan modul terdaftar di menu dan halaman modul', function () {
    $this->actingAs($this->user)->get('/')->assertSee('Modul Tiruan')->assertSee('data-module="dummy"', false);
    $this->actingAs($this->user)->get('/m/dummy')->assertOk()->assertSee('Modul untuk pengujian.');
});

it('menyembunyikan dan menolak modul yang dinonaktifkan', function () {
    ModuleState::for('dummy')->update(['enabled' => false]);

    $this->actingAs($this->user)->get('/')->assertDontSee('data-module="dummy"', false);
    $this->actingAs($this->user)->get('/m/dummy')->assertNotFound();
});

it('modul tak terdaftar tidak dapat diakses', function () {
    $this->actingAs($this->user)->get('/m/tidak-ada')->assertNotFound();
});

it('aturan akses per user mengalahkan aturan per prodi dan status modul', function () {
    ModuleState::for('dummy')->update(['enabled' => false]);
    ModuleAccessRule::create(['module_key' => 'dummy', 'scope' => 'prodi', 'scope_id' => $this->user->prodi_id, 'allowed' => true]);
    $this->actingAs($this->user)->get('/m/dummy')->assertOk();

    ModuleAccessRule::create(['module_key' => 'dummy', 'scope' => 'user', 'scope_id' => $this->user->id, 'allowed' => false]);
    $this->actingAs($this->user)->get('/m/dummy')->assertNotFound();
});

it('membuat satu Job per rencana dari planJobs modul', function () {
    Queue::fake();
    $session = dummySession($this->user);

    $tasks = app(SessionLauncher::class)->launch($session);

    expect($tasks)->toHaveCount(3)
        ->and(collect($tasks)->pluck('key')->all())->toBe(['item:A1', 'item:B2', 'sesi:rekap']);
    Queue::assertPushed(RunGenerationTask::class, 3);
    expect($session->fresh()->status)->toBe(GenerationSession::STATUS_QUEUED);
});

it('dapat menjalankan hanya satu butir (uji coba) tanpa Job tingkat sesi', function () {
    Queue::fake();
    $session = dummySession($this->user);
    $item = $session->items()->first();

    $tasks = app(SessionLauncher::class)->launch($session, [$item->id], includeSessionLevel: false);

    expect($tasks)->toHaveCount(1)->and($tasks[0]->generation_item_id)->toBe($item->id);
});

it('menjalankan langkah berurutan dalam container yang sama dan menyimpan dokumen', function () {
    $fake = fakeClaude()->queue(okResponse('Catatan_A1.docx', 'A1'), okResponse('Ringkasan_A1.docx', 'A1'));
    $session = dummySession($this->user, ['A1']);
    $task = app(SessionLauncher::class)->launch($session, includeSessionLevel: false)[0];

    expect($task->fresh()->status)->toBe(GenerationTask::STATUS_DONE);

    // Langkah 1: container baru + container_upload; langkah 2: container yang sama + percakapan lanjutan.
    [$first, $second] = $fake->requests;
    expect($first->containerId)->toBeNull()
        ->and($first->messages[0]['content'][0])->toMatchArray(['type' => 'container_upload'])
        ->and($first->system)->toBe('SISTEM catatan')
        ->and($second->containerId)->toBe('cntr_1')
        ->and($second->messages)->toHaveCount(3)
        ->and(collect($second->messages[2]['content'])->pluck('type')->all())->toBe(['text']);

    expect($fake->uploaded)->toHaveCount(1)
        ->and(array_values($fake->uploaded)[0]['filename'])->toBe('sumber.docx');

    $docs = GeneratedDocument::orderBy('id')->get();
    expect($docs)->toHaveCount(2)
        ->and($docs->pluck('doc_type')->all())->toBe(['catatan', 'ringkasan'])
        ->and($docs[0]->filename)->toBe('Catatan_A1.docx')
        ->and($docs[0]->status)->toBe(GeneratedDocument::STATUS_OK)
        ->and($docs[0]->report)->toBe(['kode' => 'A1']);
    Storage::disk('local')->assertExists($docs[0]->path);

    expect(AiUsage::count())->toBe(2)
        ->and(AiUsage::first()->cost_usd)->toBeGreaterThan(0);
    expect($session->fresh()->status)->toBe(GenerationSession::STATUS_DONE);
});

it('menandai Perlu Perhatian bila pemeriksa modul gagal atau laporan tidak ada', function () {
    fakeClaude()->queue(
        ['text' => 'tanpa laporan', 'files' => ['Catatan_A1.docx' => Fake::filler('Dokumen A1')]],
        ['text' => '```json {"kode":"A1"} ```', 'files' => ['Ringkasan_A1.docx' => Fake::filler('kode salah')]],
    );
    $session = dummySession($this->user, ['A1']);
    app(SessionLauncher::class)->launch($session, includeSessionLevel: false);

    [$catatan, $ringkasan] = GeneratedDocument::orderBy('id')->get();
    expect($catatan->status)->toBe(GeneratedDocument::STATUS_ATTENTION)
        ->and(collect($catatan->failedChecks())->pluck('key')->all())->toBe(['laporan'])
        ->and($ringkasan->status)->toBe(GeneratedDocument::STATUS_ATTENTION)
        ->and(collect($ringkasan->failedChecks())->pluck('key')->all())->toBe(['kode']);
    expect($session->fresh()->status)->toBe(GenerationSession::STATUS_ATTENTION);
});

it('mencatat dokumen gagal bila Claude tidak menghasilkan berkas', function () {
    fakeClaude()->queue(['text' => 'maaf'], okResponse('Ringkasan_A1.docx', 'A1'));
    app(SessionLauncher::class)->launch(dummySession($this->user, ['A1']), includeSessionLevel: false);

    expect(GeneratedDocument::first()->status)->toBe(GeneratedDocument::STATUS_FAILED);
});

it('melanjutkan dari langkah terakhir setelah proses terputus', function () {
    Queue::fake();
    $fake = fakeClaude()->queue(okResponse('Catatan_A1.docx', 'A1'), new ClaudeException('overloaded', transient: true, status: 529));
    $session = dummySession($this->user, ['A1']);
    $task = app(SessionLauncher::class)->launch($session, includeSessionLevel: false)[0];

    (new RunGenerationTask($task->id))->handle(app(GenerationRunner::class), app(Settings::class));
    $task->refresh();
    expect($task->current_step)->toBe(1)->and($task->status)->toBe(GenerationTask::STATUS_WAITING);

    $fake->queue(okResponse('Ringkasan_A1.docx', 'A1'));
    (new RunGenerationTask($task->id))->handle(app(GenerationRunner::class), app(Settings::class));

    expect($task->fresh()->status)->toBe(GenerationTask::STATUS_DONE)
        ->and($fake->requests)->toHaveCount(3)
        ->and($fake->requests[2]->containerId)->toBe('cntr_1')
        ->and(GeneratedDocument::where('doc_type', 'catatan')->count())->toBe(1);
});

it('memulai container baru bila container kedaluwarsa', function () {
    $fake = fakeClaude()->queue(
        okResponse('Catatan_A1.docx', 'A1'),
        new ClaudeException('container expired', transient: false, status: 400),
        okResponse('Ringkasan_A1.docx', 'A1'),
    );
    app(SessionLauncher::class)->launch(dummySession($this->user, ['A1']), includeSessionLevel: false);

    expect($fake->requests)->toHaveCount(3)
        ->and($fake->requests[2]->containerId)->toBeNull()
        ->and($fake->requests[2]->messages[0]['content'][0]['type'])->toBe('container_upload')
        ->and(GenerationTask::first()->status)->toBe(GenerationTask::STATUS_DONE);
});

it('menggagalkan task pada galat permanen', function () {
    fakeClaude()->queue(new ClaudeException('invalid request', transient: false, status: 400));
    app(SessionLauncher::class)->launch(dummySession($this->user, ['A1']), includeSessionLevel: false);

    expect(GenerationTask::first())->status->toBe(GenerationTask::STATUS_FAILED)->error->toContain('invalid request');
});

it('memeriksa ulang status akun di dalam Job', function () {
    Queue::fake();
    $fake = fakeClaude();
    $task = app(SessionLauncher::class)->launch(dummySession($this->user, ['A1']), includeSessionLevel: false)[0];

    $this->user->forceFill(['status' => AccountStatus::Ditangguhkan])->save();
    (new RunGenerationTask($task->id))->handle(app(GenerationRunner::class), app(Settings::class));

    expect($task->fresh()->status)->toBe(GenerationTask::STATUS_BLOCKED)->and($fake->requests)->toBeEmpty();
});

it('memeriksa ulang sakelar global dan sakelar modul di dalam Job', function (Closure $close) {
    Queue::fake();
    $fake = fakeClaude();
    $task = app(SessionLauncher::class)->launch(dummySession($this->user, ['A1']), includeSessionLevel: false)[0];

    $close();
    (new RunGenerationTask($task->id))->handle(app(GenerationRunner::class), app(Settings::class));

    expect($task->fresh()->status)->toBe(GenerationTask::STATUS_BLOCKED)->and($fake->requests)->toBeEmpty();
})->with([
    'global' => fn () => app(Settings::class)->set('generate_enabled', false),
    'modul' => fn () => ModuleState::for('dummy')->update(['generate_open' => false]),
    'akses' => fn () => ModuleState::for('dummy')->update(['enabled' => false]),
]);

it('menolak memulai generate bila sakelar global ditutup', function () {
    app(Settings::class)->set('generate_enabled', false);

    app(SessionLauncher::class)->launch(dummySession($this->user));
})->throws(RuntimeException::class);

it('menghentikan Job bila kuota per user habis di tengah jalan', function () {
    $fake = fakeClaude()->queue(okResponse('Catatan_A1.docx', 'A1'));
    UsageLimit::create(['scope' => 'user', 'user_id' => $this->user->id, 'max_generate_per_day' => 1]);

    app(SessionLauncher::class)->launch(dummySession($this->user, ['A1']), includeSessionLevel: false);

    expect(GenerationTask::first())->status->toBe(GenerationTask::STATUS_BLOCKED)->error->toContain('per hari')
        ->and($fake->requests)->toHaveCount(1);
});

it('menerapkan batas token per bulan modul', function () {
    AiUsage::create(['user_id' => $this->user->id, 'module_key' => 'dummy', 'kind' => 'generate', 'model' => 'claude-sonnet-5-5', 'input_tokens' => 5000]);
    UsageLimit::create(['scope' => 'module', 'module_key' => 'dummy', 'max_tokens_per_month' => 1000]);

    app(SessionLauncher::class)->launch(dummySession($this->user));
})->throws(RuntimeException::class, 'token');

it('membatasi jumlah Job yang berjalan bersamaan', function () {
    Queue::fake();
    $fake = fakeClaude();
    app(Settings::class)->set('max_concurrent_jobs', 1);
    $session = dummySession($this->user, ['A1', 'B2']);
    [$t1, $t2] = app(SessionLauncher::class)->launch($session, includeSessionLevel: false);
    $t1->forceFill(['status' => GenerationTask::STATUS_RUNNING, 'heartbeat_at' => now()])->save();

    $job = new RunGenerationTask($t2->id);
    $job->withFakeQueueInteractions()->handle(app(GenerationRunner::class), app(Settings::class));

    $job->assertReleased(30);
    expect($t2->fresh()->status)->toBe(GenerationTask::STATUS_WAITING)->and($fake->requests)->toBeEmpty();
});

it('menambahkan langkah generate ulang di container yang sama dan membuat versi baru', function () {
    $fake = fakeClaude()->queue(okResponse('Catatan_A1.docx', 'A1'), okResponse('Ringkasan_A1.docx', 'A1'), okResponse('Catatan_A1.docx', 'A1'));
    $session = dummySession($this->user, ['A1']);
    $task = app(SessionLauncher::class)->launch($session, includeSessionLevel: false)[0];

    app(SessionLauncher::class)->appendAndRun($task->fresh(), new JobStep(
        'ulang', 'Generate ulang', 'Ubah bobot UTS menjadi 25%', 'catatan', ['sumber'], 'Catatan_A1.docx', meta: ['instruction' => 'Ubah bobot UTS menjadi 25%'],
    ));

    $versions = GeneratedDocument::where('doc_type', 'catatan')->orderBy('version')->get();
    expect($versions)->toHaveCount(2)
        ->and($versions[1]->version)->toBe(2)
        ->and($versions[1]->parent_id)->toBe($versions[0]->id)
        ->and($versions[1]->instruction)->toBe('Ubah bobot UTS menjadi 25%')
        ->and($fake->requests[2]->containerId)->toBe('cntr_1');
    Storage::disk('local')->assertExists($versions[0]->path);
});

it('menghapus berkas masukan dari Files API setelah semua task selesai bila diatur', function () {
    $fake = fakeClaude()->queue(okResponse('Catatan_A1.docx', 'A1'), okResponse('Ringkasan_A1.docx', 'A1'), okResponse('Rekap.docx', 'x'));
    $session = dummySession($this->user, ['A1']);
    app(SessionLauncher::class)->launch($session);

    expect($fake->deleted)->toBe(array_keys($fake->uploaded))
        ->and($session->fresh()->option('remote_files'))->toBe([]);
});

it('membatasi laju aksi generate per pengguna', function () {
    Queue::fake();
    config(['platform.generate_rate_per_minute' => 2]);
    $launcher = app(SessionLauncher::class);
    $launcher->launch(dummySession($this->user, ['A1']));
    $launcher->launch(dummySession($this->user, ['A1']));

    expect(fn () => $launcher->launch(dummySession($this->user, ['A1'])))->toThrow(RuntimeException::class, 'Terlalu banyak');
});
