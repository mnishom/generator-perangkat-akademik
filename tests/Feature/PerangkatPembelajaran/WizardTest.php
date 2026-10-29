<?php

use App\Core\Curriculum\CurriculumExtractor;
use App\Core\Curriculum\Jobs\ExtractCurriculum;
use App\Livewire\Curriculum\CurriculumEditor;
use App\Livewire\Modules\ModuleHome;
use App\Livewire\Wizard\PromptStep;
use App\Models\CurriculumExtraction;
use App\Models\GenerationSession;
use App\Models\GenerationTask;
use App\Models\LibraryFile;
use App\Models\ModuleState;
use App\Models\User;
use App\Modules\PerangkatPembelajaran\PerangkatPembelajaranModule as Module;
use App\Modules\PerangkatPembelajaran\Steps;
use App\Services\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeClaudeDocumentClient as Fake;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    Storage::fake('local');
    registerModules(Module::class);
    $this->user = User::factory()->create();
});

it('menjalankan alur wizard dari sesi baru sampai generate massal', function () {
    Queue::fake();
    $fake = fakeClaude()->queue(['text' => 'ok', 'files' => ['kurikulum.json' => file_get_contents(base_path('tests/Fixtures/kurikulum.json'))]]);
    $u = $this->user;

    // Mulai sesi
    Livewire::actingAs($u)->test(ModuleHome::class, ['module' => Module::KEY])->call('start')->assertRedirect();
    $session = GenerationSession::sole();
    $this->actingAs($u)->get(route('modules.wizard', [Module::KEY, $session]))->assertOk()->assertSee('Data sesi');
    $p = ['sessionId' => $session->id];

    // 1. Data sesi
    Livewire::actingAs($u)->test(Steps\SessionDataStep::class, $p)
        ->set('form.tahun_akademik', '2025-2026')->call('continue')->assertHasErrors('form.tahun_akademik')
        ->set('form.tahun_akademik', '2025/2026')->set('form.nama_kaprodi', '')->call('continue')->assertHasNoErrors()->assertDispatched('wizard-next');
    expect($session->fresh())->tahun_akademik->toBe('2025/2026')->and($session->fresh()->identity['nama_kaprodi'])->toBe('');

    // 2–4. Berkas
    $docx = fn ($n) => UploadedFile::fake()->createWithContent($n, Fake::docx('isi'));
    Livewire::actingAs($u)->test(Steps\KurikulumStep::class, $p)->call('continue')->assertHasErrors('uploads.kurikulum')
        ->set('uploads.kurikulum', $docx('Kurikulum OBE 2025.docx'))->assertHasNoErrors()->call('continue')->assertDispatched('wizard-next');
    Livewire::actingAs($u)->test(Steps\ContohRpsStep::class, $p)->set('uploads.contoh_rps', $docx('RPS PBO.docx'))->call('continue')->assertDispatched('wizard-next');
    $tpl = Livewire::actingAs($u)->test(Steps\TemplateStep::class, $p)->call('continue')->assertHasErrors('uploads.template_rps');
    $tpl->set('uploads.template_kontrak', $docx('Template Kontrak.docx'))->set('uploads.template_silabus', $docx('Template Silabus.docx'))
        ->call('continue')->assertHasErrors('uploads.template_kontrak');
    $tpl->set('uploads.template_rps', $docx('Template RPS.docx'))->call('continue')->assertDispatched('wizard-next');
    expect(array_keys($session->fresh()->inputs))->toEqualCanonicalizing(['kurikulum', 'contoh_rps', 'template_rps', 'template_kontrak', 'template_silabus']);

    // 5. Ekstraksi & verifikasi
    $step = Livewire::actingAs($u)->test(Steps\VerifikasiStep::class, $p)->call('startExtraction');
    Queue::assertPushed(ExtractCurriculum::class);
    $extraction = CurriculumExtraction::sole();
    (new ExtractCurriculum($extraction->id))->handle(app(CurriculumExtractor::class));
    $step->call('continue')->assertHasErrors('verify'); // belum diverifikasi

    Livewire::actingAs($u)->test(CurriculumEditor::class, ['extractionId' => $extraction->id])->call('verify');
    Livewire::actingAs($u)->test(Steps\VerifikasiStep::class, $p)
        ->set('selectedCodes', ['DTI3019', 'DTI1003'])
        ->set('generateSilabus', true)
        ->call('continue')->assertHasNoErrors()->assertDispatched('wizard-next');
    $session->refresh();
    expect($session->items)->toHaveCount(4)
        ->and($session->items->where('selected', true)->pluck('ref_code')->sort()->values()->all())->toBe(['DTI1003', 'DTI3019'])
        ->and($session->option('generate_silabus'))->toBeTrue();

    // 6. Prompt — peringatan variabel hilang
    Livewire::actingAs($u)->test(PromptStep::class, $p)
        ->assertSee('Prompt RPS &amp; Kontrak Perkuliahan', false)->assertSee('Prompt Silabus Prodi')
        ->set('bodies.rps_kontrak', 'Buat RPS {{nama_mk}}')
        ->assertSee('tidak lagi memuat variabel penting')
        ->call('resetDefault', 'rps_kontrak')
        ->assertDontSee('tidak lagi memuat variabel penting')
        ->call('continue')->assertDispatched('wizard-next');
    expect($session->fresh()->prompts)->toHaveKeys(['rps_kontrak', 'silabus']);

    // 7. Uji coba satu MK
    Livewire::actingAs($u)->test(Steps\UjiCobaStep::class, $p)->call('run');
    $trial = GenerationTask::sole();
    expect($trial->key)->toBe('mk:DTI1003')->and($session->fresh()->option('trial_task_id'))->toBe($trial->id);

    // Anggap uji coba selesai tanpa dokumen gagal → tidak diulang di generate massal.
    $trial->update(['status' => 'done']);

    // 8. Generate massal
    Livewire::actingAs($u)->test(Steps\GenerateStep::class, $p)->call('start')->assertRedirect(route('sessions.show', $session));
    expect(GenerationTask::pluck('key')->sort()->values()->all())->toBe(['mk:DTI1003', 'mk:DTI3019', 'silabus'])
        ->and($session->fresh()->option('wizard_done'))->toBeTrue();
});

it('menolak memulai generate saat sakelar modul ditutup dan saat akun ditangguhkan', function () {
    Queue::fake();
    $session = ppSession($this->user);
    ModuleState::for(Module::KEY)->update(['generate_open' => false]);

    Livewire::actingAs($this->user)->test(Steps\GenerateStep::class, ['sessionId' => $session->id])->call('start')->assertDispatched('toast');
    expect(GenerationTask::count())->toBe(0);

    ModuleState::for(Module::KEY)->update(['generate_open' => true]);
    $this->user->forceFill(['status' => 'ditangguhkan'])->save();
    Livewire::actingAs($this->user)->test(Steps\GenerateStep::class, ['sessionId' => $session->id])->call('start');
    expect(GenerationTask::count())->toBe(0);
});

it('menyembunyikan tombol sesi baru saat sakelar global ditutup', function () {
    app(Settings::class)->set('generate_enabled', false);
    $this->actingAs($this->user)->get('/m/'.Module::KEY)->assertOk()->assertDontSee('+ Sesi generate baru')->assertSee('ditutup sementara');
});

it('pengguna lain tidak dapat membuka wizard atau langkah sesi orang lain', function () {
    $session = ppSession($this->user);
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('modules.wizard', [Module::KEY, $session]))->assertForbidden();
    Livewire::actingAs($other)->test(Steps\GenerateStep::class, ['sessionId' => $session->id])->assertForbidden();
});

it('memakai ulang ekstraksi terverifikasi untuk dokumen kurikulum yang sama', function () {
    $old = ppSession($this->user);
    $extraction = CurriculumExtraction::find($old->curriculum_extraction_id);
    $file = LibraryFile::find($old->inputs['kurikulum']);
    $extraction->update(['sha256' => $file->sha256]);

    $new = GenerationSession::create(['module_key' => Module::KEY, 'user_id' => $this->user->id, 'prodi_id' => $this->user->prodi_id, 'inputs' => ['kurikulum' => $file->id]]);
    Livewire::actingAs($this->user)->test(Steps\VerifikasiStep::class, ['sessionId' => $new->id])
        ->assertSee('Pakai data terverifikasi ini')
        ->call('useReusable', $extraction->id);

    expect($new->fresh()->curriculum_extraction_id)->toBe($extraction->id);
});
