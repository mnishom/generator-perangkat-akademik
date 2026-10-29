<?php

use App\Core\Generation\DocumentRegenerator;
use App\Core\Generation\SessionLauncher;
use App\Livewire\Sessions\History;
use App\Livewire\Sessions\SessionShow;
use App\Models\AuditLog;
use App\Models\GeneratedDocument;
use App\Models\User;
use App\Modules\PerangkatPembelajaran\PerangkatPembelajaranModule as Module;
use App\Services\Settings;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeClaudeDocumentClient as Fake;

require_once __DIR__.'/../PerangkatPembelajaran/Helpers.php';

beforeEach(function () {
    Storage::fake('local');
    registerModules(Module::class);
    $this->user = User::factory()->create();
});

/** Sesi dengan RPS + Kontrak DTI3019; RPS sengaja "Perlu Perhatian" (tanpa laporan). */
function generatedSession(User $user, bool $rpsOk = false)
{
    $session = ppSession($user, ['template_rps', 'template_kontrak'], selected: ['DTI3019'], silabus: false);
    $mk = $session->items->firstWhere('ref_code', 'DTI3019')->input;
    $fake = fakeClaude()->queue(
        ['text' => $rpsOk ? rpsReport($mk) : 'tanpa laporan', 'files' => ['RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx' => rpsText($mk)]],
        ['text' => kontrakReport(), 'files' => ['Kontrak_DTI3019_Pemrograman-Berorientasi-Objek.docx' => "Kontrak {$mk['nama']} {$mk['kode']}\n".Fake::filler()]],
    );
    app(SessionLauncher::class)->launch($session);

    return [$session->fresh(), $fake, $mk];
}

it('riwayat hanya menampilkan sesi milik pengguna; admin dapat melihat semua dengan audit', function () {
    [$mine] = generatedSession($this->user);
    $other = User::factory()->create();
    $theirs = ppSession($other);
    $theirs->update(['title' => 'Sesi Orang Lain']);

    Livewire::actingAs($this->user)->test(History::class)->assertSee('Uji')->assertDontSee('Sesi Orang Lain')
        ->set('all', true)->assertDontSee('Sesi Orang Lain');

    $admin = User::factory()->admin()->create();
    Livewire::actingAs($admin)->test(History::class)->set('all', true)->assertSee('Sesi Orang Lain');
    expect(AuditLog::where('action', 'history.view_all')->exists())->toBeTrue();
});

it('memfilter riwayat berdasarkan status dan tahun akademik', function () {
    [$s] = generatedSession($this->user);
    $draft = ppSession($this->user);
    $draft->update(['title' => 'Draf Lain', 'tahun_akademik' => '2024/2025']);

    Livewire::actingAs($this->user)->test(History::class)
        ->set('tahun', '2024/2025')->assertSee('Draf Lain')->assertDontSee('>Uji<', false)
        ->set('tahun', '')->set('status', 'attention')->assertDontSee('Draf Lain');
});

it('rincian sesi menampilkan dokumen per MK dengan laporan pemeriksaan dan tombol perbaikan', function () {
    [$session] = generatedSession($this->user);

    $this->actingAs($this->user)->get(route('sessions.show', $session))->assertOk()
        ->assertSee('RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx')
        ->assertSee('Kontrak_DTI3019_Pemrograman-Berorientasi-Objek.docx')
        ->assertSee('Perlu Perhatian')
        ->assertSee('Perbaiki otomatis')
        ->assertSee('wajib ditinjau dosen dan prodi');
});

it('pengguna lain tidak dapat melihat rincian sesi, dokumen, maupun ZIP; admin hanya baca dengan audit', function () {
    [$session] = generatedSession($this->user);
    $doc = GeneratedDocument::first();
    $other = User::factory()->create(['prodi_id' => $this->user->prodi_id]);

    $this->actingAs($other)->get(route('sessions.show', $session))->assertForbidden();
    $this->actingAs($other)->get(route('documents.download', $doc))->assertForbidden();
    $this->actingAs($other)->get(route('sessions.zip', $session))->assertForbidden();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('sessions.show', $session))->assertOk()->assertSee('Mode baca')->assertDontSee('Generate ulang');
    expect(AuditLog::where('action', 'session.view')->where('actor_id', $admin->id)->exists())->toBeTrue();
    Livewire::actingAs($admin)->test(SessionShow::class, ['session' => $session])->call('openRegenerate', $doc->id)->assertForbidden();
});

it('mengunduh ZIP per sesi dan per MK berisi versi terbaru dengan nama berkas terstruktur', function () {
    [$session] = generatedSession($this->user);
    $item = $session->items->firstWhere('ref_code', 'DTI3019');

    $res = $this->actingAs($this->user)->get(route('sessions.zip', $session))->assertOk();
    $zip = new ZipArchive;
    $zip->open($res->baseResponse->getFile()->getPathname());
    $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->sort()->values()->all();
    expect($names)->toBe(['DTI3019/Kontrak_DTI3019_Pemrograman-Berorientasi-Objek.docx', 'DTI3019/RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx']);

    $this->actingAs($this->user)->get(route('sessions.zip.item', [$session, $item]))->assertOk()->assertDownload('dti3019-pemrograman-berorientasi-objek.zip');
});

it('generate ulang dengan instruksi tambahan membuat versi baru di container yang sama', function () {
    [$session, $fake, $mk] = generatedSession($this->user, rpsOk: true);
    $rps = GeneratedDocument::where('doc_type', 'rps')->sole();
    $fake->queue(['text' => rpsReport($mk), 'files' => ['RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx' => rpsText($mk, ['UTS 25%'])]]);

    Livewire::actingAs($this->user)->test(SessionShow::class, ['session' => $session])
        ->call('openRegenerate', $rps->id)->set('instruction', 'ubah bobot UTS menjadi 25%')->call('regenerate')->assertHasNoErrors();

    $req = end($fake->requests);
    expect($req->containerId)->toBe('cntr_1')
        ->and(collect(collect($req->messages)->last()['content'])->last()['text'])->toContain('ubah bobot UTS menjadi 25%')->toContain('$OUTPUT_DIR/RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx')->toContain('komponen_penilaian');
    $versions = GeneratedDocument::where('doc_type', 'rps')->orderBy('version')->get();
    expect($versions)->toHaveCount(2)
        ->and($versions[1]->instruction)->toBe('ubah bobot UTS menjadi 25%')
        ->and($versions[1]->parent_id)->toBe($versions[0]->id);
    Storage::disk('local')->assertExists($versions[0]->path);
});

it('perbaikan otomatis mengirim daftar masalah dan dibatasi 2 kali, berikutnya manual', function () {
    [$session, $fake] = generatedSession($this->user);
    $regen = app(DocumentRegenerator::class);

    for ($i = 1; $i <= 2; $i++) {
        $doc = GeneratedDocument::where('doc_type', 'rps')->orderByDesc('version')->first();
        expect($regen->autofixAllowed($doc))->toBeTrue();
        $fake->queue(['text' => 'masih tanpa laporan', 'files' => ['RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx' => rpsText($session->items->first()->input)]]);
        Livewire::actingAs($this->user)->test(SessionShow::class, ['session' => $session])->call('autofix', $doc->id);
        $prompt = collect(collect(end($fake->requests)->messages)->last()['content'])->last()['text'];
        expect($prompt)->toContain('Laporan pemeriksaan Claude')->toContain('Perbaiki');
    }

    $latest = GeneratedDocument::where('doc_type', 'rps')->orderByDesc('version')->first();
    expect($latest->version)->toBe(3)->and($latest->autofix_count)->toBe(2)
        ->and($regen->autofixAllowed($latest))->toBeFalse();

    Livewire::actingAs($this->user)->test(SessionShow::class, ['session' => $session])->call('autofix', $latest->id)->assertDispatched('toast', type: 'error');
    expect(GeneratedDocument::where('doc_type', 'rps')->count())->toBe(3);
});

it('menandai dokumen final dari rincian sesi', function () {
    [$session] = generatedSession($this->user, rpsOk: true);
    $doc = GeneratedDocument::where('doc_type', 'rps')->sole();

    Livewire::actingAs($this->user)->test(SessionShow::class, ['session' => $session])->call('toggleFinal', $doc->id);

    expect($doc->fresh()->is_final)->toBeTrue();
    $this->actingAs($this->user)->get(route('library.documents'))->assertSee('RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx');
});

it('menolak generate ulang saat sakelar global ditutup', function () {
    [$session] = generatedSession($this->user, rpsOk: true);
    app(Settings::class)->set('generate_enabled', false);
    $doc = GeneratedDocument::where('doc_type', 'rps')->sole();

    Livewire::actingAs($this->user)->test(SessionShow::class, ['session' => $session])
        ->call('openRegenerate', $doc->id)->set('instruction', 'x')->call('regenerate')->assertHasErrors('instruction');
});
