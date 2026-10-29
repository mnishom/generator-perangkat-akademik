<?php

use App\Core\Curriculum\AnomalyDetector;
use App\Core\Curriculum\CurriculumData;
use App\Core\Curriculum\CurriculumExtractor;
use App\Livewire\Curriculum\CurriculumEditor;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\DummyModule;

/*
| Meniru ciri dokumen kurikulum S1 Akuntansi (docs/referensi): MK peminatan yang
| tercantum di beberapa tabel, CPL per MK tersirat dari peta bobot CPMK→CPL,
| bahan kajian berkode, dan catatan beda data antar-tabel.
*/

beforeEach(function () {
    $this->raw = json_decode(file_get_contents(base_path('tests/Fixtures/kurikulum-akuntansi.json')), true);
    $this->data = CurriculumData::normalize($this->raw);
});

it('menggabungkan MK yang tercantum di beberapa peminatan menjadi satu', function () {
    $mk = collect($this->data['mata_kuliah'])->where('kode', 'SAK3035');

    expect($mk)->toHaveCount(1)
        ->and($mk->first()['peminatan'])->toBe(['Akuntansi Keuangan', 'Auditing'])
        ->and($mk->first()['cpmk'][0]['kode'])->toBe('CPMK100')
        ->and(count($this->data['mata_kuliah']))->toBe(7);
});

it('menurunkan CPL per MK dari peta CPMK→CPL dan menyimpan bobotnya', function () {
    $agama = collect($this->data['mata_kuliah'])->firstWhere('kode', 'SAK2001');

    expect(array_column($agama['cpl'], 'kode'))->toBe(['CPL5', 'CPL6'])
        ->and($agama['cpl'][1]['rumusan'])->toStartWith('Lulusan mampu menunjukkan integritas')
        ->and($agama['cpmk'][0]['cpl'])->toBe(['CPL5', 'CPL6'])
        ->and($agama['cpmk'][0]['bobot_cpl'])->toBe(['CPL5' => 25.0, 'CPL6' => 5.0]);

    expect(CurriculumData::courseToText($agama))
        ->toContain('Peminatan: wajib (semua peminatan)')
        ->toContain('CPMK1: Mahasiswa mampu menjelaskan konsep dasar nilai keimanan')
        ->toContain('[→ CPL5 (25%), CPL6 (5%)]')
        ->toContain('BK93 – Etika dan Nilai Keagamaan');
});

it('menghitung total SKS per jalur peminatan, tanpa galat palsu kode ganda atau total SKS', function () {
    $a = app(AnomalyDetector::class)->detect($this->data);
    $messages = collect($a['global'])->pluck('message')->implode(' | ').' '.collect($a['courses'])->flatten(1)->pluck('message')->implode(' | ');

    expect($a['tracks'])->toBe(['Akuntansi Keuangan' => 29, 'Auditing' => 29])
        ->and($messages)->not->toContain('ganda')
        ->and($messages)->not->toContain('tidak sama dengan total SKS')
        ->and($messages)->not->toContain('Tidak ada CPL yang dibebankan')
        ->and($messages)->toContain('Catatan ekstraksi: SAK2022');
});

it('menandai jalur peminatan yang jumlah SKS-nya tidak sesuai dokumen', function () {
    $this->raw['prodi']['total_sks'] = 30;
    $a = app(AnomalyDetector::class)->detect(CurriculumData::normalize($this->raw));

    expect(collect($a['global'])->pluck('message')->implode(' '))
        ->toContain('jalur peminatan Akuntansi Keuangan (29) tidak sama dengan total SKS pada dokumen kurikulum (30)');
});

it('daftar MK untuk Silabus memuat keterangan peminatan', function () {
    expect(CurriculumData::coursesToList($this->data['mata_kuliah']))
        ->toContain('- SAK3035 AKUNTANSI KEUANGAN LANJUTAN 1 (3 SKS; Teori); peminatan: Akuntansi Keuangan, Auditing')
        ->toContain('- SAK1244 MAGANG PROFESIONAL AKUNTANSI (12 SKS; Magang/PKL)');
});

it('editor mempertahankan bobot CPL dan peminatan saat MK disunting', function () {
    Storage::fake('local');
    registerModules(DummyModule::class);
    $user = User::factory()->create();
    fakeClaude()->queue(['text' => 'ok', 'files' => ['kurikulum.json' => json_encode($this->raw)]]);
    $e = app(CurriculumExtractor::class)->start(libraryFile($user, 'kurikulum', 'kurikulum.pdf'), $user, 'dummy')->fresh();
    $i = collect($e->courses())->search(fn ($mk) => $mk['kode'] === 'SAK3054');

    Livewire::actingAs($user)->test(CurriculumEditor::class, ['extractionId' => $e->id])
        ->call('edit', $i)
        ->assertSet('form.peminatan', 'Auditing')
        ->assertSet('form.cpmk.0.cpl', 'CPL4:100')
        ->set('form.peminatan', 'Auditing, Akuntansi Manajemen')
        ->set('form.cpmk.0.cpl', 'CPL4:80, CPL3:20')
        ->call('saveCourse')
        ->assertHasNoErrors();

    $mk = $e->fresh()->course('SAK3054');
    expect($mk['peminatan'])->toBe(['Auditing', 'Akuntansi Manajemen'])
        ->and($mk['cpmk'][0]['cpl'])->toBe(['CPL4', 'CPL3'])
        ->and($mk['cpmk'][0]['bobot_cpl'])->toEqual(['CPL4' => 80, 'CPL3' => 20]);
});

it('prompt ekstraksi memuat panduan peminatan, CPL turunan, bahan kajian berkode, dan beda data antar-tabel', function () {
    $prompt = app(CurriculumExtractor::class)->prompt('DRAF Panduan Kurikulum OBE Prodi S1 AKUNTANSI (Final 2026).pdf');

    expect($prompt)->toContain('SATU KALI')->toContain('peminatan')->toContain('bobot_cpl')
        ->toContain('kode – nama')->toContain('catatan_ekstraksi')->toContain('tabel distribusi');
});
