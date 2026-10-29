<?php

namespace Tests\Support;

use App\Core\Claude\ClaudeDocumentClient;
use App\Core\Claude\ClaudeException;
use App\Core\Claude\GenerateRequest;
use App\Core\Claude\GenerateResult;
use Carbon\CarbonImmutable;
use Closure;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Pengganti ClaudeDocumentClient untuk test: tidak pernah memanggil API sungguhan.
 *
 * Setiap generate() memakai respons berikutnya dari antrean (fixture). Respons
 * dapat berupa array spesifikasi, ClaudeException (dilempar), atau Closure.
 */
class FakeClaudeDocumentClient extends ClaudeDocumentClient
{
    /** @var array<int, GenerateRequest> */
    public array $requests = [];

    public array $uploaded = [];

    public array $deleted = [];

    /** @var array<string, string> file_id => isi berkas */
    public array $files = [];

    private array $queue = [];

    private int $containerSeq = 0;

    private int $fileSeq = 0;

    public function __construct()
    {
        parent::__construct([]);
    }

    /**
     * Antrekan respons generate.
     *
     * @param  array{text?: string, files?: array<string, string>, container?: string|null, stop?: string, usage?: array}|ClaudeException|Closure  $response
     *                                                                                                                                                        `files`: nama berkas => teks isi dokumen (dibuat sebagai DOCX)
     */
    public function queue(array|ClaudeException|Closure ...$responses): static
    {
        array_push($this->queue, ...$responses);

        return $this;
    }

    public function uploadFile(string $path, string $filename, string $mime): string
    {
        $id = 'file_up_'.(++$this->fileSeq);
        $this->uploaded[$id] = ['path' => $path, 'filename' => $filename, 'mime' => $mime];

        return $id;
    }

    public function deleteFile(string $fileId): void
    {
        $this->deleted[] = $fileId;
    }

    public function fileMetadata(string $fileId): array
    {
        return ['id' => $fileId, 'filename' => $this->files[$fileId.'#name'] ?? null, 'mime_type' => null, 'size_bytes' => null];
    }

    public function downloadFile(string $fileId): string
    {
        return $this->files[$fileId] ?? throw new ClaudeException("File {$fileId} tidak ada", false, 404);
    }

    public function generate(GenerateRequest $request): GenerateResult
    {
        $this->requests[] = $request;
        $spec = array_shift($this->queue) ?? ['text' => 'OK'];

        if ($spec instanceof Closure) {
            $spec = $spec($request, $this);
        }
        if ($spec instanceof ClaudeException) {
            throw $spec;
        }

        $containerId = array_key_exists('container', $spec)
            ? $spec['container']
            : ($request->containerId ?? 'cntr_'.(++$this->containerSeq));

        $outputFiles = [];
        foreach ($spec['files'] ?? [] as $name => $text) {
            $id = 'file_out_'.(++$this->fileSeq);
            $this->files[$id] = ! str_ends_with($name, '.docx') || str_starts_with($text, 'PK') ? $text : self::docx($text);
            $this->files[$id.'#name'] = $name;
            $outputFiles[] = ['file_id' => $id, 'filename' => $spec['omit_filename'] ?? false ? null : $name];
        }

        $text = $spec['text'] ?? '';
        $messages = $request->messages;
        $messages[] = ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => $text]]];

        return new GenerateResult(
            messages: $messages,
            text: $text,
            stopReason: $spec['stop'] ?? 'end_turn',
            containerId: $containerId,
            containerExpiresAt: CarbonImmutable::now()->addDays(30),
            outputFiles: $outputFiles,
            usage: $spec['usage'] ?? ['input_tokens' => 1000, 'output_tokens' => 500, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
        );
    }

    /** Buat DOCX sungguhan berisi teks (satu paragraf per baris). */
    public static function docx(string $text): string
    {
        $word = new PhpWord;
        $section = $word->addSection();
        foreach (preg_split('/\R/', $text) as $line) {
            $section->addText(htmlspecialchars($line, ENT_XML1));
        }
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        IOFactory::createWriter($word, 'Word2007')->save($tmp);
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    /** Teks contoh yang cukup panjang agar lolos pemeriksaan ukuran minimum. */
    public static function filler(string $head = ''): string
    {
        return $head."\n".str_repeat("Isi dokumen uji yang cukup panjang untuk pemeriksaan ukuran. \n", 20);
    }
}
