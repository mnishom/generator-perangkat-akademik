<?php

namespace App\Console\Commands;

use App\Core\Claude\ClaudeDocumentClient;
use App\Core\Claude\GenerateRequest;
use App\Core\Usage\UsageRecorder;
use App\Core\Validation\DocxText;
use App\Core\Validation\ReportParser;
use Illuminate\Console\Command;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Throwable;

/**
 * Uji integrasi nyata (opt-in): unggah satu DOCX kecil, minta Claude membaca
 * dan menghasilkan DOCX baru dengan skill docx, unduh, periksa, lalu bersihkan.
 * Memakai kuota API sungguhan.
 */
class ClaudeSmokeTest extends Command
{
    protected $signature = 'claude:smoke-test {--model= : Model yang diuji} {--keep : Jangan hapus berkas di Files API} {--force : Tanpa konfirmasi}';

    protected $description = 'Uji integrasi nyata Claude API (Files API + code execution + skill docx). Memakai kuota API.';

    public function handle(ClaudeDocumentClient $client): int
    {
        if (! $this->option('force') && ! $this->confirm('Perintah ini memanggil Claude API sungguhan dan memakai kuota. Lanjutkan?')) {
            return self::FAILURE;
        }

        $model = $this->option('model') ?: config('claude.model');
        $this->info("Model: {$model}");
        $uploaded = null;
        $outputs = [];

        try {
            $word = new PhpWord;
            $word->addSection()->addText('Mata kuliah: Uji Integrasi (UJI001), 2 SKS, semester 1.');
            $tmp = tempnam(sys_get_temp_dir(), 'smoke').'.docx';
            IOFactory::createWriter($word, 'Word2007')->save($tmp);

            $this->line('1. Unggah berkas ke Files API…');
            $uploaded = $client->uploadFile($tmp, 'masukan-uji.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            $this->line("   file_id: {$uploaded}");

            $this->line('2. Generate DOCX dengan skill docx…');
            $started = microtime(true);
            $result = $client->generate(new GenerateRequest($model, [[
                'role' => 'user',
                'content' => [
                    ['type' => 'container_upload', 'file_id' => $uploaded],
                    ['type' => 'text', 'text' => 'Baca berkas "masukan-uji.docx". Buat dokumen Word bernama "hasil-uji.docx" berisi judul "Hasil Uji" dan satu tabel dengan kolom Kode, Nama, SKS yang diisi dari berkas tersebut. Simpan berkas ke $OUTPUT_DIR. Akhiri jawaban dengan blok ```json {"kode_mk": "...", "sks": 0} ```.'],
                ],
            ]], skills: ['docx']));
            $this->line(sprintf('   selesai dalam %.1f detik, %d permintaan, stop_reason=%s, container=%s', microtime(true) - $started, $result->requests, $result->stopReason, $result->containerId));

            $cost = UsageRecorder::cost($model, $result->usage);
            $this->line(sprintf('   token input %d, output %d (estimasi $%.4f)', $result->inputTokens(), $result->outputTokens(), $cost));
            app(UsageRecorder::class)->record($result, $model, ['kind' => 'smoke']);

            if (! $result->outputFiles) {
                $this->error('Tidak ada berkas keluaran. Jawaban Claude:');
                $this->line($result->text);

                return self::FAILURE;
            }

            $this->line('3. Unduh berkas hasil…');
            foreach ($result->outputFiles as $f) {
                $meta = $client->fileMetadata($f['file_id']);
                $outputs[] = $f['file_id'];
                $bytes = $client->downloadFile($f['file_id']);
                $path = storage_path('app/private/smoke-'.($meta['filename'] ?? $f['file_id']));
                file_put_contents($path, $bytes);
                $this->line("   {$meta['filename']} (".strlen($bytes)." bita) → {$path}");
                if (str_ends_with((string) $meta['filename'], '.docx')) {
                    $text = DocxText::extract($path);
                    $this->line('   isi mengandung UJI001: '.(str_contains($text, 'UJI001') ? 'ya' : 'TIDAK'));
                }
            }

            $this->line('4. Laporan JSON: '.json_encode(ReportParser::parse($result->text)));
            $this->info('Uji integrasi BERHASIL.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Uji integrasi GAGAL: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            if ($uploaded && ! $this->option('keep')) {
                foreach (array_merge([$uploaded], $outputs) as $id) {
                    try {
                        $client->deleteFile($id);
                    } catch (Throwable) {
                    }
                }
                $this->line('Berkas di Files API dibersihkan.');
            }
        }
    }
}
