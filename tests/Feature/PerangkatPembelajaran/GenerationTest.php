<?php

use App\Core\Generation\SessionLauncher;
use App\Models\GeneratedDocument;
use App\Models\GenerationSession;
use App\Models\GenerationTask;
use App\Models\User;
use App\Modules\PerangkatPembelajaran\PerangkatPembelajaranModule as Module;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeClaudeDocumentClient as Fake;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    Storage::fake('local');
    registerModules(Module::class);
    $this->user = User::factory()->create();
});

function mkData(GenerationSession $s, string $kode): array
{
    return $s->items->firstWhere('ref_code', $kode)->input;
}

it('menghasilkan RPS lalu Kontrak di container yang sama dan lolos pemeriksaan lapis 1 & 2', function () {
    $session = ppSession($this->user, selected: ['DTI3019'], silabus: false);
    $mk = mkData($session, 'DTI3019');
    $fake = fakeClaude()->queue(
        ['text' => rpsReport($mk), 'files' => ['RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx' => rpsText($mk)]],
        ['text' => kontrakReport(), 'files' => ['Kontrak_DTI3019_Pemrograman-Berorientasi-Objek.docx' => "KONTRAK PERKULIAHAN\nMata Kuliah: {$mk['nama']}\nKode: {$mk['kode']}\n".Fake::filler()]],
    );

    app(SessionLauncher::class)->launch($session);

    expect($fake->requests)->toHaveCount(2)
        ->and($fake->requests[1]->containerId)->toBe('cntr_1')
        ->and(collect($fake->uploaded)->pluck('filename')->sort()->values()->all())
        ->toBe(['DRAF Panduan Kurikulum OBE.docx', 'RPS PBO.docx', 'Template Kontrak.docx', 'Template RPS.docx']);

    $rps = GeneratedDocument::where('doc_type', 'rps')->sole();
    $kontrak = GeneratedDocument::where('doc_type', 'kontrak')->sole();
    expect($rps->status)->toBe(GeneratedDocument::STATUS_OK, json_encode($rps->failedChecks()))
        ->and($kontrak->status)->toBe(GeneratedDocument::STATUS_OK, json_encode($kontrak->failedChecks()))
        ->and($rps->filename)->toBe('RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx')
        ->and($rps->ref_code)->toBe('DTI3019')
        ->and(collect($rps->checks)->pluck('key')->all())->toContain('docx_valid', 'kode_mk', 'nama_mk', 'sks', 'cpmk_lengkap', 'cpmk_asing', 'bobot', 'subcpmk', 'placeholder');
    expect($session->fresh()->status)->toBe(GenerationSession::STATUS_DONE);
});

it('menandai Perlu Perhatian bila SKS/CPMK salah, ada CPMK MK lain, atau placeholder tersisa', function () {
    $session = ppSession($this->user, ['template_rps'], selected: ['DTI3019'], silabus: false);
    $mk = mkData($session, 'DTI3019');
    $bad = $mk;
    $bad['sks']['total'] = 4;
    $bad['cpmk'] = [$mk['cpmk'][0], ['kode' => 'CPMK0011', 'rumusan' => 'milik Logika']];
    fakeClaude()->queue(['text' => rpsReport($mk, ['sks' => 4]), 'files' => [
        'RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx' => rpsText($bad, ['Metode: [isi metode pembelajaran]', 'Uraian materi ......................']),
    ]]);

    app(SessionLauncher::class)->launch($session);

    $rps = GeneratedDocument::sole();
    $failed = collect($rps->failedChecks())->keyBy('key');
    expect($rps->status)->toBe(GeneratedDocument::STATUS_ATTENTION)
        ->and($failed->keys()->all())->toContain('sks', 'cpmk_lengkap', 'cpmk_asing', 'placeholder', 'laporan_sks')
        ->and($failed['cpmk_lengkap']['message'])->toContain('CPMK0192')
        ->and($failed['cpmk_asing']['message'])->toContain('CPMK0011 (DTI1001)')
        ->and($failed['placeholder']['message'])->toContain('[isi metode pembelajaran]');
    expect($session->fresh()->status)->toBe(GenerationSession::STATUS_ATTENTION);
});

it('menandai Kontrak bila komponen dan bobot penilaian berbeda dengan RPS', function () {
    $session = ppSession($this->user, ['template_rps', 'template_kontrak'], selected: ['DTI3019'], silabus: false);
    $mk = mkData($session, 'DTI3019');
    fakeClaude()->queue(
        ['text' => rpsReport($mk), 'files' => ['RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx' => rpsText($mk)]],
        ['text' => kontrakReport([['nama' => 'Tugas', 'bobot' => 20], ['nama' => 'UTS', 'bobot' => 30], ['nama' => 'UAS', 'bobot' => 50]]), 'files' => [
            'Kontrak_DTI3019_Pemrograman-Berorientasi-Objek.docx' => "Kontrak {$mk['nama']} {$mk['kode']}\n".Fake::filler(),
        ]],
    );

    app(SessionLauncher::class)->launch($session);

    $kontrak = GeneratedDocument::where('doc_type', 'kontrak')->sole();
    expect($kontrak->status)->toBe(GeneratedDocument::STATUS_ATTENTION)
        ->and(collect($kontrak->failedChecks())->firstWhere('key', 'kontrak_rps')['message'])->toContain('tugas: RPS 30% vs Kontrak 20%');
});

it('menandai RPS bila total bobot bukan 100% atau ada CPMK tanpa Sub-CPMK', function () {
    $session = ppSession($this->user, ['template_rps'], selected: ['DTI3019'], silabus: false);
    $mk = mkData($session, 'DTI3019');
    fakeClaude()->queue(['text' => rpsReport($mk, ['total_bobot' => 90, 'bobot_konsisten' => false, 'cpmk_tanpa_subcpmk' => ['CPMK0192']]), 'files' => [
        'RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx' => rpsText($mk),
    ]]);

    app(SessionLauncher::class)->launch($session);

    expect(collect(GeneratedDocument::sole()->failedChecks())->pluck('key')->all())->toContain('bobot', 'subcpmk');
});

it('menyusun Silabus bertahap dalam satu container dan memeriksa kelengkapan MK', function () {
    $session = ppSession($this->user, ['template_silabus'], selected: []);
    $codes = $session->items->pluck('ref_code')->all();
    $fake = fakeClaude()->queue(
        ['text' => 'Semester 1 ditambahkan.'],
        ['text' => 'Semester 3 ditambahkan.'],
        ['text' => "Selesai.\n```json\n".json_encode(['jumlah_mk_di_kurikulum' => 4, 'jumlah_mk_di_dokumen' => 3, 'mk_terlewat' => ['DTI4040'], 'catatan' => []])."\n```",
            'files' => ['Silabus_D4-Teknik-Informatika_2025-2026.docx' => "SILABUS\n".implode("\n", array_diff($codes, ['DTI4040']))."\n".Fake::filler()]],
    );

    app(SessionLauncher::class)->launch($session);

    expect($fake->requests)->toHaveCount(3)
        ->and($fake->requests[1]->containerId)->toBe('cntr_1')
        ->and($fake->requests[2]->containerId)->toBe('cntr_1')
        ->and(count($fake->requests[2]->messages))->toBe(5); // percakapan berlanjut

    $silabus = GeneratedDocument::sole();
    expect($silabus->doc_type)->toBe('silabus')
        ->and($silabus->generation_item_id)->toBeNull()
        ->and($silabus->filename)->toBe('Silabus_D4-Teknik-Informatika_2025-2026.docx')
        ->and($silabus->status)->toBe(GeneratedDocument::STATUS_ATTENTION)
        ->and(collect($silabus->failedChecks())->pluck('key')->all())->toContain('silabus_lengkap', 'silabus_laporan', 'silabus_jumlah')
        ->and(collect($silabus->failedChecks())->firstWhere('key', 'silabus_lengkap')['message'])->toContain('DTI4040');
    expect(GenerationTask::sole()->generation_item_id)->toBeNull();
});

it('Silabus lengkap lolos pemeriksaan', function () {
    $session = ppSession($this->user, ['template_silabus'], selected: []);
    $codes = $session->items->pluck('ref_code')->all();
    fakeClaude()->queue(['text' => 'ok'], ['text' => 'ok'], [
        'text' => "```json\n".json_encode(['jumlah_mk_di_kurikulum' => 4, 'jumlah_mk_di_dokumen' => 4, 'mk_terlewat' => [], 'catatan' => []])."\n```",
        'files' => ['Silabus_D4-Teknik-Informatika_2025-2026.docx' => "SILABUS\n".implode("\n", $codes)."\n".Fake::filler()],
    ]);

    app(SessionLauncher::class)->launch($session);

    expect(GeneratedDocument::sole()->status)->toBe(GeneratedDocument::STATUS_OK);
});
