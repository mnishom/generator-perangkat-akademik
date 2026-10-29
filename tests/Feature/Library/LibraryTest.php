<?php

use App\Core\Library\DocumentLibrary;
use App\Livewire\Library\FileLibraryPage;
use App\Models\GeneratedDocument;
use App\Models\GenerationSession;
use App\Models\LibraryFile;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\DummyModule;
use Tests\Support\FakeClaudeDocumentClient as Fake;

beforeEach(function () {
    Storage::fake('local');
    registerModules(DummyModule::class);
});

function docxUpload(string $name = 'Kurikulum OBE.docx'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, Fake::docx('isi'));
}

it('mengunggah berkas DOCX valid ke pustaka prodi', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(FileLibraryPage::class)
        ->set('role', 'sumber')
        ->set('upload', docxUpload('Kurikulum: OBE*.docx'))
        ->assertHasNoErrors();

    $file = LibraryFile::sole();
    expect($file->prodi_id)->toBe($user->prodi_id)
        ->and($file->is_shared)->toBeFalse()
        ->and($file->original_name)->toBe('Kurikulum OBE.docx')
        ->and($file->role)->toBe('sumber');
    Storage::disk('local')->assertExists($file->path);
});

it('menolak ekstensi yang tidak diizinkan', function () {
    Livewire::actingAs(User::factory()->create())->test(FileLibraryPage::class)
        ->set('role', 'sumber')
        ->set('upload', UploadedFile::fake()->create('virus.exe', 10))
        ->assertHasErrors('upload');

    expect(LibraryFile::count())->toBe(0);
});

it('menolak berkas yang isinya bukan DOCX walau berekstensi .docx', function () {
    Livewire::actingAs(User::factory()->create())->test(FileLibraryPage::class)
        ->set('role', 'sumber')
        ->set('upload', UploadedFile::fake()->createWithContent('palsu.docx', 'bukan zip'))
        ->assertHasErrors('upload');
});

it('menolak berkas melebihi batas ukuran dari pengaturan admin', function () {
    app(Settings::class)->set('max_upload_mb', 1);
    $big = UploadedFile::fake()->create('besar.pdf', 2048, 'application/pdf');

    Livewire::actingAs(User::factory()->create())->test(FileLibraryPage::class)
        ->set('role', 'sumber')
        ->set('upload', $big)
        ->assertHasErrors('upload');
});

it('hanya admin yang dapat membuat berkas bersama', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user)->test(FileLibraryPage::class)->set('role', 'sumber')->set('shared', true)->set('upload', docxUpload());
    expect(LibraryFile::sole()->is_shared)->toBeFalse();

    $admin = User::factory()->admin()->create();
    Livewire::actingAs($admin)->test(FileLibraryPage::class)->set('role', 'sumber')->set('shared', true)->set('upload', docxUpload());
    expect(LibraryFile::where('is_shared', true)->count())->toBe(1);
});

it('mengisolasi pustaka berkas antar prodi; berkas bersama terlihat semua', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $own = libraryFile($a, 'sumber', 'milik-a.docx');
    $shared = libraryFile($a, 'sumber', 'bersama.docx');
    $shared->forceFill(['is_shared' => true, 'prodi_id' => null])->save();

    $this->actingAs($b)->get(route('library.download', $own))->assertForbidden();
    $this->actingAs($b)->get(route('library.download', $shared))->assertOk();
    $this->actingAs($a)->get(route('library.download', $own))->assertOk();

    Livewire::actingAs($b)->test(FileLibraryPage::class)->assertSee('bersama.docx')->assertDontSee('milik-a.docx');
});

it('hanya pengunggah atau admin yang dapat menghapus berkas', function () {
    $a = User::factory()->create();
    $rekan = User::factory()->create(['prodi_id' => $a->prodi_id]);
    $file = libraryFile($a, 'sumber');

    Livewire::actingAs($rekan)->test(FileLibraryPage::class)->call('delete', $file->id)->assertForbidden();
    Livewire::actingAs($a)->test(FileLibraryPage::class)->call('delete', $file->id);

    expect(LibraryFile::count())->toBe(0)->and(LibraryFile::withTrashed()->count())->toBe(1);
});

it('pustaka dokumen hanya memuat dokumen final milik pengguna atau prodinya', function () {
    $a = User::factory()->create();
    $rekan = User::factory()->create(['prodi_id' => $a->prodi_id]);
    $lain = User::factory()->create();
    $session = GenerationSession::create(['module_key' => 'dummy', 'user_id' => $a->id, 'prodi_id' => $a->prodi_id]);
    $make = fn (int $v) => GeneratedDocument::create([
        'generation_session_id' => $session->id, 'user_id' => $a->id, 'prodi_id' => $a->prodi_id,
        'module_key' => 'dummy', 'doc_type' => 'rekap', 'version' => $v, 'path' => "x/v{$v}.docx", 'filename' => "Rekap-v{$v}.docx", 'ref_code' => 'R1',
    ]);
    $v1 = $make(1);
    $v2 = $make(2);

    $library = app(DocumentLibrary::class);
    $library->markFinal($v1);
    $library->markFinal($v2);

    expect($v1->fresh()->is_final)->toBeFalse()->and($v2->fresh()->is_final)->toBeTrue();
    expect($library->finals($rekan)->pluck('id')->all())->toBe([$v2->id])
        ->and($library->finals($lain))->toBeEmpty()
        ->and($library->latestFinal($a, 'rekap', 'R1')?->id)->toBe($v2->id);

    $this->actingAs($lain)->get(route('documents.download', $v2))->assertForbidden();
});
