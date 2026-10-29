<?php

use App\Core\Validation\CheckResult;
use App\Core\Validation\DocxText;
use App\Core\Validation\ValidationContext;
use App\Core\Validation\Validators\LeftoverPlaceholderValidator;
use App\Models\GeneratedDocument;
use App\Models\GenerationSession;
use App\Models\LibraryFile;
use App\Models\User;
use App\Modules\PerangkatPembelajaran\PerangkatPembelajaranModule as Module;
use App\Modules\PerangkatPembelajaran\Validators\CourseIdentityValidator;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeClaudeDocumentClient as Fake;

require_once __DIR__.'/Helpers.php';

/*
| Memakai template resmi di docs/referensi/ untuk memastikan pemeriksa lapis 2
| mengenali gaya penanda isian template universitas.
*/

const REF = 'docs/referensi/';

function refPath(string $name): string
{
    return base_path(REF.$name);
}

/** Jalankan satu validator atas berkas DOCX apa adanya. */
function checkFile(string $path, $validator, array $mk = []): CheckResult
{
    Storage::fake('local');
    $user = User::factory()->create();
    $session = GenerationSession::create(['module_key' => Module::KEY, 'user_id' => $user->id]);
    $item = $session->items()->create(['ref_code' => $mk['kode'] ?? 'X', 'input' => $mk]);
    Storage::disk('local')->put('d.docx', file_get_contents($path));
    $doc = GeneratedDocument::create(['generation_session_id' => $session->id, 'generation_item_id' => $item->id, 'user_id' => $user->id, 'module_key' => Module::KEY, 'doc_type' => 'rps', 'path' => 'd.docx']);

    $results = $validator->validate(new ValidationContext($doc, $session, $item, null));

    return collect($results)->firstWhere('key', $validator instanceof LeftoverPlaceholderValidator ? 'placeholder' : 'sks') ?? $results[0];
}

beforeEach(function () {
    if (! is_file(refPath('4.2. Template RPS OBE Edisi 1 Tahun 2025.docx'))) {
        $this->markTestSkipped('Berkas referensi tidak tersedia.');
    }
});

it('mendeteksi penanda isian pada template resmi yang belum diisi', function (string $file, array $expect) {
    $result = checkFile(refPath($file), new LeftoverPlaceholderValidator);

    expect($result->passed)->toBeFalse();
    foreach ($expect as $snippet) {
        expect($result->message)->toContain($snippet);
    }
})->with([
    'RPS' => ['4.2. Template RPS OBE Edisi 1 Tahun 2025.docx', ['Lulusan mampu']],
    'Kontrak' => ['4.4. Template Kontrak Perkuliahan Edisi 1 Tahun 2025.docx', ['[nama program studi]']],
    'Silabus' => ['4.5. Template Silabus Edisi 1 Tahun 2025.docx', ['Petunjuk']],
]);

it('menjaga label dan isi satu baris tabel tetap dalam satu baris teks', function () {
    $text = DocxText::extract(refPath('4.4. Template Kontrak Perkuliahan Edisi 1 Tahun 2025.docx'));

    expect($text)->toMatch('/Mata Kuliah\s*\t\s*:\s*\t\s*\[tuliskan nama mata kuliah\]/u')
        ->toMatch('/Dosen Pengampu\s*\t\s*:\s*\t\s*\[tuliskan nama dosen pengampu\]/u');
});

it('tidak menandai kop fakultas/prodi dan tempat tanda tangan yang memang dikosongkan', function () {
    $doc = Fake::docx(implode("\n", [
        'UNIVERSITAS HARKAT NEGERI, FAKULTAS ……….. PROGRAM STUDI ………',
        'Dosen Pengampu: ……………………',
        '(………………………………………)',
        'Isi dokumen lengkap.',
    ]));
    $tmp = tempnam(sys_get_temp_dir(), 'd');
    file_put_contents($tmp, $doc);

    expect(checkFile($tmp, new LeftoverPlaceholderValidator)->passed)->toBeTrue();
});

it('tetap menandai nama MK/tahun akademik yang dibiarkan bertitik-titik', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'd');
    file_put_contents($tmp, Fake::docx('Kontrak ini menjadi dasar mahasiswa dan dosen untuk Mata Kuliah ………… pada tahun akademik ………'));

    expect(checkFile($tmp, new LeftoverPlaceholderValidator)->passed)->toBeFalse();
});

it('mengenali SKS dalam format template T=, P=, L=, S=', function (string $line, bool $ok) {
    $tmp = tempnam(sys_get_temp_dir(), 'd');
    file_put_contents($tmp, Fake::docx("Pemrograman Berorientasi Objek DTI3019\nBOBOT (SKS)\n{$line}"));
    $mk = ['kode' => 'DTI3019', 'nama' => 'Pemrograman Berorientasi Objek', 'sks' => ['total' => 3, 'teori' => null, 'praktikum' => 3, 'lapangan' => null, 'seminar' => null]];

    expect(checkFile($tmp, new CourseIdentityValidator, $mk)->passed)->toBe($ok);
})->with([
    ['T=, P=3, L=, S=', true],
    ['T=0, P=3, L=0, S=0', true],
    ['T=1, P=2, L=, S=', false],
    ['T=, P=2, L=, S=', false],
]);

it('memuat skill pdf bila contoh RPS berformat PDF', function () {
    Storage::fake('local');
    registerModules(Module::class);
    $user = User::factory()->create();
    $session = ppSession($user, ['template_rps'], silabus: false);
    LibraryFile::find($session->inputs['contoh_rps'])->update(['extension' => 'pdf', 'original_name' => 'Contoh RPS isi Lengkap.pdf']);

    $plans = app(Module::class)->planJobs($session->fresh());

    expect($plans[0]->skills)->toBe(['docx', 'pdf'])
        ->and($plans[0]->steps[0]->prompt)->toStartWith('Contoh RPS isi Lengkap.pdf = Sample RPS.');
});
