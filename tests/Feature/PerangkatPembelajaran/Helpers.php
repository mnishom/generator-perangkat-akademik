<?php

use App\Core\Curriculum\CurriculumData;
use App\Models\CurriculumExtraction;
use App\Models\GenerationSession;
use App\Models\User;
use App\Modules\PerangkatPembelajaran\PerangkatPembelajaranModule;
use Tests\Support\FakeClaudeDocumentClient as Fake;

/** Sesi Modul 1 siap generate: berkas, ekstraksi terverifikasi, dan butir MK. */
function ppSession(User $user, array $templates = ['template_rps', 'template_kontrak', 'template_silabus'], array $selected = ['DTI3019', 'DTI1003'], bool $silabus = true): GenerationSession
{
    $inputs = [
        'kurikulum' => libraryFile($user, 'kurikulum', 'DRAF Panduan Kurikulum OBE.docx')->id,
        'contoh_rps' => libraryFile($user, 'contoh_rps', 'RPS PBO.docx')->id,
    ];
    $names = ['template_rps' => 'Template RPS.docx', 'template_kontrak' => 'Template Kontrak.docx', 'template_silabus' => 'Template Silabus.docx'];
    foreach ($templates as $t) {
        $inputs[$t] = libraryFile($user, $t, $names[$t])->id;
    }

    $data = CurriculumData::normalize(json_decode(file_get_contents(base_path('tests/Fixtures/kurikulum.json')), true));
    $extraction = CurriculumExtraction::create([
        'library_file_id' => $inputs['kurikulum'], 'sha256' => 'x', 'prodi_id' => $user->prodi_id, 'user_id' => $user->id,
        'status' => 'verified', 'data' => $data, 'anomalies' => [], 'verified_by' => $user->id, 'verified_at' => now(),
    ]);

    $session = GenerationSession::create([
        'module_key' => PerangkatPembelajaranModule::KEY, 'user_id' => $user->id, 'prodi_id' => $user->prodi_id,
        'curriculum_extraction_id' => $extraction->id, 'title' => 'Uji', 'tanggal_penyusunan' => '2026-10-29',
        'tahun_akademik' => '2025/2026', 'inputs' => $inputs, 'status' => 'draft',
        'identity' => ['nama_fakultas' => 'Fakultas Teknik', 'nama_prodi' => 'D4 Teknik Informatika', 'nama_kaprodi' => '', 'nama_dekan' => 'Dr. Dekan'],
        'options' => ['generate_silabus' => $silabus],
    ]);
    foreach ($data['mata_kuliah'] as $i => $mk) {
        $session->items()->create(['ref_code' => $mk['kode'], 'ref_label' => $mk['nama'], 'input' => $mk, 'selected' => in_array($mk['kode'], $selected, true), 'sort' => $i]);
    }

    return $session->fresh();
}

/** Teks DOCX RPS yang "benar" untuk sebuah MK fixture. */
function rpsText(array $mk, array $extra = []): string
{
    $lines = ['RENCANA PEMBELAJARAN SEMESTER', "Mata Kuliah: {$mk['nama']}", "Kode MK: {$mk['kode']}", "Bobot: {$mk['sks']['total']} SKS", 'Dosen Pengampu: ..................'];
    foreach ($mk['cpmk'] as $c) {
        $lines[] = "{$c['kode']}: {$c['rumusan']}";
        $lines[] = "Sub-{$c['kode']}.1 Mahasiswa mampu menguraikan.";
    }

    return implode("\n", array_merge($lines, $extra))."\n".Fake::filler();
}

function rpsReport(array $mk, array $over = []): string
{
    $r = array_merge([
        'kode_mk' => $mk['kode'], 'sks' => $mk['sks']['total'], 'cpmk_digunakan' => array_column($mk['cpmk'], 'kode'),
        'komponen_penilaian' => [['nama' => 'Tugas', 'bobot' => 30], ['nama' => 'UTS', 'bobot' => 30], ['nama' => 'UAS', 'bobot' => 40]],
        'total_bobot' => 100, 'bobot_konsisten' => true, 'jumlah_pertemuan' => 16, 'minggu_uts' => 8, 'minggu_uas' => 16,
        'cpmk_tanpa_subcpmk' => [], 'metode_pembelajaran' => ['PjBL'], 'pustaka_perlu_verifikasi' => 1, 'catatan' => [],
    ], $over);

    return "RPS selesai.\n```json\n".json_encode($r)."\n```";
}

function kontrakReport(array $komponen = [['nama' => 'Tugas', 'bobot' => 30], ['nama' => 'UTS', 'bobot' => 30], ['nama' => 'UAS', 'bobot' => 40]], bool $konsisten = true): string
{
    return "Kontrak selesai.\n```json\n".json_encode(['komponen_penilaian' => $komponen, 'konsisten_dengan_rps' => $konsisten, 'catatan' => []])."\n```";
}
