<?php

use App\Core\Generation\Jobs\RunGenerationTask;
use App\Core\Generation\SessionLauncher;
use App\Core\Prompts\PromptEngine;
use App\Models\User;
use App\Modules\PerangkatPembelajaran\PerangkatPembelajaranModule as Module;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    Storage::fake('local');
    registerModules(Module::class);
    $this->user = User::factory()->create();
    $this->module = app(Module::class);
});

it('membuat satu Job per MK terpilih (RPS → Kontrak) dan satu Job Silabus per sesi', function () {
    Queue::fake();
    $session = ppSession($this->user);

    $tasks = app(SessionLauncher::class)->launch($session);

    expect(collect($tasks)->pluck('key')->sort()->values()->all())->toBe(['mk:DTI1003', 'mk:DTI3019', 'silabus']);
    Queue::assertPushed(RunGenerationTask::class, 3);

    $mk = collect($tasks)->firstWhere('key', 'mk:DTI3019');
    $steps = $mk->jobSteps();
    expect($steps)->toHaveCount(2)
        ->and($steps[0]->docType)->toBe('rps')
        ->and($steps[0]->outputFilename)->toBe('RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx')
        ->and($steps[1]->docType)->toBe('kontrak')
        ->and($steps[1]->outputFilename)->toBe('Kontrak_DTI3019_Pemrograman-Berorientasi-Objek.docx')
        ->and($steps[0]->system)->toBe($steps[1]->system)
        ->and($steps[0]->inputRoles)->toEqualCanonicalizing(['kurikulum', 'contoh_rps', 'template_rps', 'template_kontrak']);
});

it('mengganti variabel prompt dengan nama berkas asli dan data MK terverifikasi', function () {
    $session = ppSession($this->user);
    $plan = collect($this->module->planJobs($session))->firstWhere('key', 'mk:DTI3019');
    $prompt = $plan->steps[0]->prompt;

    expect($prompt)
        ->toStartWith('RPS PBO.docx = Sample RPS.')
        ->toContain('mata kuliah Pemrograman Berorientasi Objek (DTI3019) yang ada di dalam dokumen "DRAF Panduan Kurikulum OBE.docx"')
        ->toContain('template dokumen RPS "Template RPS.docx"')
        ->toContain('template Kontrak Perkuliahan "Template Kontrak.docx"')
        ->toContain('Tanggal penyusunan: 29 Oktober 2026. Tahun akademik: 2025/2026.')
        ->toContain('CPMK0192: Mampu menerapkan pewarisan dan polimorfisme.')
        ->not->toContain('{{')
        ->not->toContain('CPMK0011'); // data MK lain tidak ikut
    expect($plan->steps[1]->prompt)->toContain('Kontrak Perkuliahan')->toContain('$OUTPUT_DIR/Kontrak_DTI3019_Pemrograman-Berorientasi-Objek.docx');
});

it('instruksi sistem memuat peran berkas, akurasi, identitas, dan nama berkas', function () {
    $session = ppSession($this->user);
    $system = collect($this->module->planJobs($session))->firstWhere('key', 'mk:DTI3019')->steps[0]->system;

    expect($system)
        ->toContain('"DRAF Panduan Kurikulum OBE.docx" = dokumen kurikulum OBE prodi: SUMBER DATA')
        ->toContain('"RPS PBO.docx" = contoh RPS final: ACUAN GAYA')
        ->toContain('TIDAK BOLEH disalin')
        ->toContain('"Template RPS.docx" = template RPS: FORMAT WAJIB')
        ->toContain('sama persis')
        ->toContain('Nama fakultas: Fakultas Teknik (pakai persis seperti ini)')
        ->toContain('Nama Ketua Program Studi: TIDAK TERSEDIA')
        ->toContain('Kontrak Perkuliahan WAJIB memakai komponen dan bobot penilaian')
        ->toContain('`RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx`')
        ->toContain('$OUTPUT_DIR')
        ->toContain('```json');
});

it('tanpa template Kontrak hanya membuat RPS; tanpa template RPS tidak ada Job per MK', function () {
    $s1 = ppSession($this->user, ['template_rps'], silabus: false);
    $plans = $this->module->planJobs($s1);
    expect($plans[0]->steps)->toHaveCount(1)->and($plans[0]->steps[0]->docType)->toBe('rps');

    $s2 = ppSession($this->user, ['template_silabus']);
    expect(collect($this->module->planJobs($s2))->pluck('key')->all())->toBe(['silabus']);
});

it('menyusun Silabus bertahap per semester di container yang sama dan hanya menyimpan hasil tahap terakhir', function () {
    $session = ppSession($this->user);
    $plan = collect($this->module->planJobs($session))->firstWhere('key', 'silabus');

    expect($plan->item)->toBeNull()
        ->and(collect($plan->steps)->pluck('label')->all())->toBe(['Silabus (Semester 1)', 'Silabus (Semester 3)', 'Silabus (Semester 7)']);
    [$a, $b, $c] = $plan->steps;
    expect($a->collectsOutput)->toBeFalse()->and($a->docType)->toBeNull()
        ->and($a->prompt)->toContain('Buatkan Silabus seluruh mata kuliah')->toContain('Tahap 1 dari 3')->toContain('DTI1001')->not->toContain('Data lengkap MK kelompok ini (terverifikasi):\n- Kode MK: DTI3019')
        ->and($b->prompt)->not->toContain('Buatkan Silabus seluruh mata kuliah')->toContain('Lanjutkan dokumen kerja')
        ->and($c->collectsOutput)->toBeTrue()->and($c->docType)->toBe('silabus')
        ->and($c->outputFilename)->toBe('Silabus_D4-Teknik-Informatika_2025-2026.docx')
        ->and($c->prompt)->toContain('SEMUA 4 MK');
    expect($a->prompt)->toContain('- DTI4040 Kerja Praktik (4 SKS; Magang/PKL)'); // {{daftar_mk}} memuat seluruh MK
});

it('memecah semester besar menjadi beberapa kelompok', function () {
    $courses = array_map(fn ($i) => ['kode' => "K{$i}", 'semester' => 1], range(1, 23));
    $groups = Module::silabusGroups($courses);

    expect($groups)->toHaveCount(3)
        ->and(array_column($groups, 'label'))->toBe(['Semester 1 bagian 1', 'Semester 1 bagian 2', 'Semester 1 bagian 3'])
        ->and(count($groups[2]['courses']))->toBe(3);
});

it('memperingatkan bila prompt kehilangan variabel penting', function () {
    $engine = app(PromptEngine::class);
    $vars = $this->module->promptDefinitions()['rps_kontrak']['variables'];

    expect($engine->analyze('Buat RPS {{nama_mk}} {{foo}}', $vars))->toBe(['missing' => ['kode_mk', 'data_mk'], 'unknown' => ['foo']])
        ->and($engine->analyze($engine->defaultBody($this->module, 'rps_kontrak'), $vars)['missing'])->toBe([]);
});

it('prompt default berasal dari berkas dan dapat diubah admin dengan riwayat versi serta rollback', function () {
    $engine = app(PromptEngine::class);
    $admin = User::factory()->admin()->create();
    expect($engine->defaultBody($this->module, 'rps_kontrak'))->toStartWith('{{file_contoh_rps}} = Sample RPS.');

    $engine->saveDefault($this->module, 'rps_kontrak', 'Versi baru {{nama_mk}}', $admin);
    expect($engine->defaultBody($this->module, 'rps_kontrak'))->toBe('Versi baru {{nama_mk}}')
        ->and($engine->history($this->module, 'rps_kontrak')->pluck('version')->all())->toBe([2, 1]);

    $engine->activateVersion($this->module, 'rps_kontrak', 1);
    expect($engine->defaultBody($this->module, 'rps_kontrak'))->toStartWith('{{file_contoh_rps}} = Sample RPS.');
});

it('nama berkas terstruktur', function () {
    expect(Module::filename('RPS', 'DTI3019', 'Pemrograman Berorientasi Objek'))->toBe('RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx')
        ->and(Module::filename('Kontrak', 'DTI/30 19', 'Sistem & Jaringan (Lanjut)'))->toBe('Kontrak_DTI-30-19_Sistem-Jaringan-Lanjut.docx');
});
