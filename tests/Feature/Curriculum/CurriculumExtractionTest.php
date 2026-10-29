<?php

use App\Core\Curriculum\AnomalyDetector;
use App\Core\Curriculum\CurriculumData;
use App\Core\Curriculum\CurriculumExtractor;
use App\Core\Curriculum\Jobs\ExtractCurriculum;
use App\Livewire\Curriculum\CurriculumEditor;
use App\Models\AiUsage;
use App\Models\CurriculumExtraction;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\DummyModule;

beforeEach(function () {
    Storage::fake('local');
    registerModules(DummyModule::class);
    $this->user = User::factory()->create();
    $this->json = file_get_contents(base_path('tests/Fixtures/kurikulum.json'));
});

it('mengekstrak kurikulum lewat Claude dan menyimpan data terstruktur beserta penanda', function () {
    $fake = fakeClaude()->queue(['text' => '4 MK terbaca.', 'files' => ['kurikulum.json' => $this->json]]);
    $file = libraryFile($this->user, 'kurikulum', 'Panduan Kurikulum OBE.docx');

    $extraction = app(CurriculumExtractor::class)->start($file, $this->user, 'dummy');
    $extraction->refresh();

    expect($extraction->status)->toBe(CurriculumExtraction::STATUS_EXTRACTED)
        ->and($extraction->courses())->toHaveCount(4)
        ->and($extraction->courses()[0]['kode'])->toBe('DTI1001') // diurutkan per semester
        ->and($extraction->course('DTI3019')['sks']['total'])->toBe(3);

    $req = $fake->requests[0];
    expect($req->messages[0]['content'][0]['type'])->toBe('container_upload')
        ->and($req->messages[0]['content'][1]['text'])->toContain('Panduan Kurikulum OBE.docx')
        ->and($req->messages[0]['content'][1]['text'])->toContain('$OUTPUT_DIR/kurikulum.json')
        ->and($req->skills)->toBe(['docx']);
    expect(array_values($fake->uploaded)[0]['filename'])->toBe('Panduan Kurikulum OBE.docx');
    expect(AiUsage::where('kind', 'extract')->count())->toBe(1);
});

it('memakai skill pdf untuk kurikulum berformat PDF', function () {
    $fake = fakeClaude()->queue(['text' => 'ok', 'files' => ['kurikulum.json' => $this->json]]);
    $file = libraryFile($this->user, 'kurikulum', 'kurikulum.pdf');
    $file->update(['extension' => 'pdf']);

    app(CurriculumExtractor::class)->start($file, $this->user, 'dummy');

    expect($fake->requests[0]->skills)->toBe(['pdf', 'docx']);
});

it('menandai data mencurigakan', function () {
    $data = CurriculumData::normalize(json_decode($this->json, true));
    $a = app(AnomalyDetector::class)->detect($data);

    $messages = fn (string $kode) => collect($a['courses'][$kode] ?? [])->pluck('message')->implode(' | ');

    expect($messages('DTI4040'))->toContain('MK tanpa CPMK')
        ->and($messages('DTI1003'))->toContain('CPL CPL09 yang tidak ada')
        ->and($a['courses'])->not->toHaveKey('DTI3019')
        ->and($a['total_sks'])->toBe(11)
        ->and(collect($a['global'])->pluck('message')->implode(' '))->toContain('(11) tidak sama dengan total SKS pada dokumen kurikulum (9)');
    expect(app(AnomalyDetector::class)->errorCount($a))->toBeGreaterThanOrEqual(3);
});

it('menandai rincian SKS yang tidak sesuai total dan kode MK ganda', function () {
    $data = CurriculumData::normalize(['cpl' => [['kode' => 'C1', 'rumusan' => 'x']], 'mata_kuliah' => [
        ['kode' => 'A', 'nama' => 'A', 'sks' => ['total' => 3, 'teori' => 1, 'praktikum' => 1], 'semester' => 1, 'cpl' => [['kode' => 'C1']], 'cpmk' => [['kode' => 'M1', 'rumusan' => 'r', 'cpl' => ['C1']]], 'bahan_kajian' => ['b']],
        ['kode' => 'A', 'nama' => 'B', 'sks' => ['total' => 2], 'semester' => 1, 'cpl' => [['kode' => 'C1']], 'cpmk' => [['kode' => 'M2', 'rumusan' => 'r', 'cpl' => ['C1']]], 'bahan_kajian' => ['b']],
    ]]);
    $a = app(AnomalyDetector::class)->detect($data);

    expect(collect($a['courses']['A'])->pluck('message')->implode(' '))->toContain('Rincian SKS')->toContain('ganda');
});

it('menandai gagal bila hasil bukan JSON yang sesuai', function () {
    fakeClaude()->queue(['text' => 'maaf, tidak bisa']);
    $e = app(CurriculumExtractor::class)->start(libraryFile($this->user, 'kurikulum'), $this->user, 'dummy');

    expect($e->fresh())->status->toBe(CurriculumExtraction::STATUS_FAILED)->error->toContain('JSON');
});

it('memeriksa ulang kuota/status di dalam Job ekstraksi', function () {
    Queue::fake();
    $fake = fakeClaude();
    $e = app(CurriculumExtractor::class)->start(libraryFile($this->user, 'kurikulum'), $this->user, 'dummy');
    $this->user->forceFill(['status' => 'ditangguhkan'])->save();

    (new ExtractCurriculum($e->id))->handle(app(CurriculumExtractor::class));

    expect($e->fresh()->status)->toBe(CurriculumExtraction::STATUS_BLOCKED)->and($fake->requests)->toBeEmpty();
});

it('menyunting data MK di tabel, menghitung ulang penanda, lalu memverifikasi', function () {
    fakeClaude()->queue(['text' => 'ok', 'files' => ['kurikulum.json' => $this->json]]);
    $e = app(CurriculumExtractor::class)->start(libraryFile($this->user, 'kurikulum'), $this->user, 'dummy')->fresh();
    $index = collect($e->courses())->search(fn ($mk) => $mk['kode'] === 'DTI1003');

    Livewire::actingAs($this->user)->test(CurriculumEditor::class, ['extractionId' => $e->id])
        ->call('edit', $index)
        ->set('form.sks_total', 3)
        ->set('form.cpmk.0.cpl', 'CPL01')
        ->call('saveCourse')
        ->assertHasNoErrors()
        ->call('verify')
        ->assertDispatched('curriculum-verified');

    $e->refresh();
    expect($e->status)->toBe(CurriculumExtraction::STATUS_VERIFIED)
        ->and($e->verified_by)->toBe($this->user->id)
        ->and($e->course('DTI1003')['sks']['total'])->toBe(3)
        ->and($e->course('DTI1003')['cpmk'][0]['cpl'])->toBe(['CPL01'])
        ->and(collect($e->anomalies['courses']['DTI1003'] ?? [])->pluck('message')->implode(' '))->not->toContain('CPL09');
});

it('memvalidasi isian MK yang wajib', function () {
    fakeClaude()->queue(['text' => 'ok', 'files' => ['kurikulum.json' => $this->json]]);
    $e = app(CurriculumExtractor::class)->start(libraryFile($this->user, 'kurikulum'), $this->user, 'dummy');

    Livewire::actingAs($this->user)->test(CurriculumEditor::class, ['extractionId' => $e->id])
        ->call('edit', 0)->set('form.kode', '')->call('saveCourse')->assertHasErrors('form.kode');
});

it('mengizinkan pemakaian ulang ekstraksi terverifikasi untuk dokumen yang sama', function () {
    fakeClaude()->queue(['text' => 'ok', 'files' => ['kurikulum.json' => $this->json]]);
    $file = libraryFile($this->user, 'kurikulum');
    $e = app(CurriculumExtractor::class)->start($file, $this->user, 'dummy');
    expect(app(CurriculumExtractor::class)->findReusable($file, $this->user->prodi_id))->toBeNull();

    app(CurriculumExtractor::class)->save($e->fresh(), $e->fresh()->data, $this->user, verify: true);

    $sameContent = libraryFile($this->user, 'kurikulum', 'salinan.docx');
    $sameContent->update(['sha256' => $file->sha256]);
    expect(app(CurriculumExtractor::class)->findReusable($sameContent, $this->user->prodi_id)?->id)->toBe($e->id);
});

it('menolak pengguna prodi lain membuka editor ekstraksi', function () {
    fakeClaude()->queue(['text' => 'ok', 'files' => ['kurikulum.json' => $this->json]]);
    $e = app(CurriculumExtractor::class)->start(libraryFile($this->user, 'kurikulum'), $this->user, 'dummy');

    Livewire::actingAs(User::factory()->create())->test(CurriculumEditor::class, ['extractionId' => $e->id])->assertForbidden();
});

it('membuat teks data MK yang mudah dibaca untuk prompt', function () {
    $data = CurriculumData::normalize(json_decode($this->json, true));
    $mk = collect($data['mata_kuliah'])->firstWhere('kode', 'DTI3019');
    $text = CurriculumData::courseToText($mk);

    expect($text)->toContain('Kode MK: DTI3019')->toContain('3 SKS (T=1, P=2)')->toContain('CPMK0192: Mampu menerapkan pewarisan')->toContain('[→ CPL02]');
    expect(CurriculumData::coursesToList($data['mata_kuliah']))->toContain("Semester 1:\n- DTI1001 Logika Informatika (2 SKS; Teori)");
});
