<?php

use App\Core\Claude\ClaudeDocumentClient;
use App\Core\Modules\ModuleRegistry;
use App\Models\GenerationSession;
use App\Models\LibraryFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeClaudeDocumentClient;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/** Daftarkan modul untuk test dan kosongkan cache registri. */
function registerModules(string ...$classes): void
{
    config(['modules.modules' => $classes]);
    app(ModuleRegistry::class)->flush();
}

/** Ganti ClaudeDocumentClient dengan versi palsu (tidak pernah memanggil API). */
function fakeClaude(): FakeClaudeDocumentClient
{
    $fake = new FakeClaudeDocumentClient;
    app()->instance(ClaudeDocumentClient::class, $fake);

    return $fake;
}

/** Simpan berkas pustaka palsu untuk sebuah peran. */
function libraryFile(User $user, string $role, string $name = 'sumber.docx', ?string $content = null): LibraryFile
{
    $path = 'library/test/'.uniqid().'.docx';
    Storage::disk('local')->put($path, $content ?? FakeClaudeDocumentClient::docx('isi '.$role));

    return LibraryFile::create([
        'prodi_id' => $user->prodi_id, 'uploaded_by' => $user->id, 'role' => $role,
        'original_name' => $name, 'path' => $path, 'extension' => pathinfo($name, PATHINFO_EXTENSION),
        'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size' => Storage::disk('local')->size($path), 'sha256' => hash('sha256', $path),
    ]);
}

/** Sesi modul tiruan dengan butir berkode. */
function dummySession(User $user, array $kodes = ['A1', 'B2']): GenerationSession
{
    $file = libraryFile($user, 'sumber');
    $session = GenerationSession::create([
        'module_key' => 'dummy', 'user_id' => $user->id, 'prodi_id' => $user->prodi_id,
        'inputs' => ['sumber' => $file->id], 'status' => 'draft',
    ]);
    foreach ($kodes as $i => $kode) {
        $session->items()->create(['ref_code' => $kode, 'ref_label' => "Butir {$kode}", 'input' => ['kode' => $kode], 'sort' => $i]);
    }

    return $session;
}
